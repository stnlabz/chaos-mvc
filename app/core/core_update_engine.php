<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_update_engine
{
    private string $installationRoot;
    private string $stateDirectory;
    private core_migration_database $database;
    private $syntaxChecker;
    private $bootstrapChecker;
    private $versionChecker;
    private $faultInjector;

    public function __construct(
        string $installationRoot,
        string $stateDirectory,
        core_migration_database $database,
        ?callable $syntaxChecker = null,
        ?callable $bootstrapChecker = null,
        ?callable $versionChecker = null,
        ?callable $faultInjector = null
    ) {
        $this->installationRoot = rtrim($installationRoot, '/\\');
        $this->stateDirectory = rtrim($stateDirectory, '/\\');
        $this->database = $database;
        $this->syntaxChecker = $syntaxChecker;
        $this->bootstrapChecker = $bootstrapChecker;
        $this->versionChecker = $versionChecker;
        $this->faultInjector = $faultInjector;
    }

    /**
     * Install one previously validated and staged Core package.
     *
     * @return array<string, mixed>
     */
    public function install(string $stagingId): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $stagingId)) {
            return $this->unchangedFailure('core_stage_id_invalid', 'The staged Core package identifier is invalid.');
        }

        $stagingRoot = $this->stateDirectory . '/staging/' . $stagingId;
        $stagedFilesRoot = $stagingRoot . '/files';
        $stageState = $this->readJson($stagingRoot . '/state.json');
        $newManifest = $this->readJson($stagedFilesRoot . '/core-manifest.json');
        $oldManifestPath = $this->installationRoot . '/.chaos-core-manifest.json';
        $oldManifest = $this->readJson($oldManifestPath);

        if (
            $stageState === null || ($stageState['phase'] ?? null) !== 'staged' ||
            $newManifest === null || $oldManifest === null ||
            ($stageState['target_version'] ?? null) !== ($newManifest['version'] ?? null)
        ) {
            return $this->unchangedFailure(
                'core_install_state_invalid',
                'Staged or installed Core ownership state is unavailable.'
            );
        }

        $currentVersion = (string) ($oldManifest['version'] ?? '');
        $targetVersion = (string) ($newManifest['version'] ?? '');
        $lock = new core_update_lock($this->stateDirectory);
        $lockResult = $lock->acquire($currentVersion, $targetVersion);

        if (($lockResult['success'] ?? false) !== true) {
            return [
                'success' => false,
                'outcome' => 'update_in_progress',
                'phase' => 'locking',
                'error_code' => 'core_update_in_progress',
                'message' => 'Another Core update operation is already in progress.'
            ];
        }

        $operationId = (string) $lockResult['state']['operation_id'];
        $journal = new core_update_journal($this->stateDirectory, $operationId);
        $backups = new core_backup_manager($this->installationRoot, $this->stateDirectory);
        $maintenance = new core_maintenance($this->stateDirectory);
        $installer = new core_file_installer(
            $this->installationRoot,
            $lock,
            $backups,
            $this->faultInjector
        );
        $verifier = new core_install_verifier(
            $this->installationRoot,
            $backups,
            $this->database,
            $this->syntaxChecker,
            $this->bootstrapChecker,
            $this->versionChecker
        );

        if (!$journal->append('update_started', [
            'staging_id' => $stagingId,
            'installed_version' => $currentVersion,
            'target_version' => $targetVersion
        ])) {
            $lock->release($operationId);
            return $this->unchangedFailure('core_journal_write_failed', 'The Core update journal could not be created.');
        }

        $lock->updatePhase($operationId, 'backing_up');
        $backup = $backups->create($operationId, $currentVersion, $oldManifest, $journal);

        if (($backup['success'] ?? false) !== true) {
            $lock->release($operationId);
            return $backup;
        }

        if (!$maintenance->activate($operationId, 'installing')) {
            $lock->release($operationId);
            return $this->unchangedFailure('core_maintenance_failed', 'Core maintenance mode could not be activated.');
        }

        $lock->updatePhase($operationId, 'installing');
        $files = $installer->installFiles(
            $operationId,
            $stagedFilesRoot,
            $oldManifest,
            $newManifest,
            $journal
        );

        if (($files['success'] ?? false) !== true) {
            return $this->finishFailure($files, $operationId, $lock, $maintenance);
        }

        $maintenance->update($operationId, 'migrating');
        $lock->updatePhase($operationId, 'migrating');
        $migrations = (new core_migration_runner($this->database))->run($newManifest, $stagedFilesRoot, $journal);

        if (($migrations['success'] ?? false) !== true) {
            $restored = $backups->restoreTemporary($operationId, $newManifest, $journal);
            $restored['cause_error_code'] = $migrations['error_code'] ?? 'core_migration_failed';

            return $this->finishFailure($restored, $operationId, $lock, $maintenance);
        }

        $maintenance->update($operationId, 'verifying');
        $lock->updatePhase($operationId, 'verifying');
        $preCommit = $verifier->preCommit(
            $operationId,
            $stagedFilesRoot,
            $oldManifest,
            $newManifest,
            $journal
        );

        if (($preCommit['success'] ?? false) !== true) {
            $restored = $backups->restoreTemporary($operationId, $newManifest, $journal);
            $restored['cause_error_code'] = $preCommit['error_code'] ?? 'core_precommit_verify_failed';

            return $this->finishFailure($restored, $operationId, $lock, $maintenance);
        }

        $commit = $installer->commitVersion($operationId, $stagedFilesRoot, $newManifest, $journal);

        if (($commit['success'] ?? false) !== true) {
            return $this->finishFailure($commit, $operationId, $lock, $maintenance);
        }

        $postCommit = $verifier->postCommit($newManifest, $journal);

        if (($postCommit['success'] ?? false) !== true) {
            $restored = $backups->restoreTemporary($operationId, $newManifest, $journal);
            $restored['cause_error_code'] = $postCommit['error_code'] ?? 'core_postcommit_verify_failed';

            return $this->finishFailure($restored, $operationId, $lock, $maintenance);
        }

        if (!$this->writeInstalledManifest($newManifest)) {
            $restored = $backups->restoreTemporary($operationId, $newManifest, $journal);
            $this->writeInstalledManifest($oldManifest);
            $restored['cause_error_code'] = 'core_installed_manifest_write_failed';

            return $this->finishFailure($restored, $operationId, $lock, $maintenance);
        }

        if (!$backups->promote($operationId, $journal)) {
            $restored = $backups->restoreTemporary($operationId, $newManifest, $journal);
            $this->writeInstalledManifest($oldManifest);
            $restored['cause_error_code'] = 'core_rollback_promotion_failed';

            return $this->finishFailure($restored, $operationId, $lock, $maintenance);
        }

        if (!$journal->append('update_completed', ['installed_version' => $targetVersion])) {
            // Installation is verified and rollback retained; preserve success but surface audit degradation.
            $auditWarning = 'core_completion_journal_failed';
        }

        $maintenance->deactivate($operationId);
        $lock->release($operationId);

        return [
            'success' => true,
            'outcome' => 'updated',
            'phase' => 'complete',
            'installed_version' => $targetVersion,
            'operation_id' => $operationId,
            'warning_code' => $auditWarning ?? null,
            'completed_at' => gmdate('c')
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function finishFailure(
        array $result,
        string $operationId,
        core_update_lock $lock,
        core_maintenance $maintenance
    ): array {
        if (($result['outcome'] ?? null) === 'failed_restored') {
            $maintenance->deactivate($operationId);
            $lock->release($operationId);
        }

        return $result + ['operation_id' => $operationId];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $value = json_decode((string) file_get_contents($path), true);

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeInstalledManifest(array $manifest): bool
    {
        $path = $this->installationRoot . '/.chaos-core-manifest.json';
        $temporary = $path . '.tmp.' . bin2hex(random_bytes(6));
        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false || file_put_contents($temporary, $encoded . PHP_EOL, LOCK_EX) === false) {
            @unlink($temporary);
            return false;
        }

        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function unchangedFailure(string $code, string $message): array
    {
        return [
            'success' => false,
            'outcome' => 'failed_unchanged',
            'phase' => 'installing',
            'error_code' => $code,
            'message' => $message
        ];
    }
}
/* [End AI:GPT-5.6 Sol] */
