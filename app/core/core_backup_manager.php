<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_backup_manager
{
    private string $installationRoot;
    private string $stateDirectory;

    public function __construct(string $installationRoot, string $stateDirectory)
    {
        $this->installationRoot = rtrim($installationRoot, '/\\');
        $this->stateDirectory = rtrim($stateDirectory, '/\\');
    }

    /**
     * Create a verified temporary backup for one Core operation.
     *
     * @param array<string, mixed> $installedManifest
     * @return array<string, mixed>
     */
    public function create(
        string $operationId,
        string $installedVersion,
        array $installedManifest,
        core_update_journal $journal
    ): array {
        if (!preg_match('/^[a-f0-9]{32}$/', $operationId)) {
            return $this->failure('core_backup_operation_invalid', 'The Core backup operation is invalid.');
        }

        $files = $this->validateManifest($installedManifest, $installedVersion);

        if ($files === null) {
            return $this->failure('core_installed_manifest_invalid', 'The installed Core manifest is invalid.');
        }

        $backupRoot = $this->operationBackupRoot($operationId);

        if (is_dir($backupRoot) || !@mkdir($backupRoot . '/files', 0750, true)) {
            return $this->failure('core_backup_create_failed', 'The Core backup directory could not be created.');
        }

        if (!$journal->append('backup_started', ['installed_version' => $installedVersion])) {
            $this->removeTree($backupRoot);
            return $this->failure('core_journal_write_failed', 'The Core backup journal could not be written.');
        }

        $backupFiles = [];

        foreach ($files as $path) {
            $source = $this->installationPath($path);
            $destination = $backupRoot . '/files/' . str_replace('/', DIRECTORY_SEPARATOR, $path);

            if (!is_file($source)) {
                $this->removeTree($backupRoot);
                return $this->failure('core_backup_source_missing', 'An installed Core file is missing.');
            }

            if (!is_dir(dirname($destination)) && !@mkdir(dirname($destination), 0750, true)) {
                $this->removeTree($backupRoot);
                return $this->failure('core_backup_create_failed', 'A Core backup directory could not be created.');
            }

            if (!@copy($source, $destination)) {
                $this->removeTree($backupRoot);
                return $this->failure('core_backup_copy_failed', 'An installed Core file could not be backed up.');
            }

            $hash = hash_file('sha256', $source);
            $backupHash = hash_file('sha256', $destination);
            $size = filesize($source);

            if (!is_string($hash) || !is_string($backupHash) || !hash_equals($hash, $backupHash) || !is_int($size)) {
                $this->removeTree($backupRoot);
                return $this->failure('core_backup_verify_failed', 'A Core backup file could not be verified.');
            }

            $backupFiles[] = ['path' => $path, 'sha256' => $hash, 'size' => $size];
        }

        $configSource = $this->installationRoot . '/app/core/config.php';
        $configBackup = $backupRoot . '/verification/config.php';

        if (!is_file($configSource) || (!is_dir(dirname($configBackup)) && !@mkdir(dirname($configBackup), 0750, true))) {
            $this->removeTree($backupRoot);
            return $this->failure('core_config_unavailable', 'Protected Core configuration is unavailable.');
        }

        if (!@copy($configSource, $configBackup)) {
            $this->removeTree($backupRoot);
            return $this->failure('core_config_backup_failed', 'Protected Core configuration could not be verified.');
        }

        $configHash = hash_file('sha256', $configSource);

        if (!is_string($configHash) || !hash_equals($configHash, (string) hash_file('sha256', $configBackup))) {
            $this->removeTree($backupRoot);
            return $this->failure('core_config_backup_failed', 'Protected Core configuration could not be verified.');
        }

        $manifestRaw = json_encode($installedManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $state = [
            'schema' => 1,
            'operation_id' => $operationId,
            'installed_version' => $installedVersion,
            'created_at' => gmdate('c'),
            'files' => $backupFiles,
            'installed_manifest_sha256' => hash('sha256', $manifestRaw . PHP_EOL),
            'config_sha256' => $configHash
        ];
        $stateRaw = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (
            $manifestRaw === false || $stateRaw === false ||
            file_put_contents($backupRoot . '/installed-manifest.json', $manifestRaw . PHP_EOL, LOCK_EX) === false ||
            file_put_contents($backupRoot . '/backup-state.json', $stateRaw . PHP_EOL, LOCK_EX) === false
        ) {
            $this->removeTree($backupRoot);
            return $this->failure('core_backup_state_failed', 'Core backup state could not be recorded.');
        }

        if (!$this->verifyAt($backupRoot) || !$journal->append('backup_verified', ['file_count' => count($backupFiles)])) {
            $this->removeTree($backupRoot);
            return $this->failure('core_backup_verify_failed', 'The Core backup could not be verified.');
        }

        return [
            'success' => true,
            'outcome' => 'backup_verified',
            'operation_id' => $operationId,
            'installed_version' => $installedVersion,
            'file_count' => count($backupFiles)
        ];
    }

    public function verifyTemporary(string $operationId): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $operationId) === 1
            && $this->verifyAt($this->operationBackupRoot($operationId));
    }

    public function protectedConfigurationUnchanged(string $operationId): bool
    {
        if (!$this->verifyTemporary($operationId)) {
            return false;
        }

        $state = json_decode(
            (string) file_get_contents($this->operationBackupRoot($operationId) . '/backup-state.json'),
            true
        );
        $configPath = $this->installationRoot . '/app/core/config.php';

        return is_array($state)
            && is_file($configPath)
            && hash_equals(
                (string) ($state['config_sha256'] ?? ''),
                (string) hash_file('sha256', $configPath)
            );
    }

    /**
     * Promote a verified temporary backup as the single retained rollback.
     */
    public function promote(string $operationId, core_update_journal $journal): bool
    {
        if (!$this->verifyTemporary($operationId)) {
            return false;
        }

        $source = $this->operationBackupRoot($operationId);
        $rollback = $this->stateDirectory . '/rollback';
        $oldRollback = $this->stateDirectory . '/rollback.previous.' . $operationId;

        if (is_dir($oldRollback)) {
            return false;
        }

        if (is_dir($rollback) && !@rename($rollback, $oldRollback)) {
            return false;
        }

        if (!@rename($source, $rollback)) {
            if (is_dir($oldRollback)) {
                @rename($oldRollback, $rollback);
            }
            return false;
        }

        if (!$this->verifyAt($rollback)) {
            @rename($rollback, $source);
            if (is_dir($oldRollback)) {
                @rename($oldRollback, $rollback);
            }
            return false;
        }

        if (!$journal->append('rollback_promoted', ['retained_versions' => 1])) {
            @rename($rollback, $source);
            if (is_dir($oldRollback)) {
                @rename($oldRollback, $rollback);
            }
            return false;
        }

        if (is_dir($oldRollback)) {
            $this->removeTree($oldRollback);
        }

        return true;
    }

    public function verifyRollback(): bool
    {
        return $this->verifyAt($this->stateDirectory . '/rollback');
    }

    /**
     * Restore a verified operation backup and remove only new Core-owned files.
     *
     * @param array<string, mixed> $newManifest
     * @return array<string, mixed>
     */
    public function restoreTemporary(
        string $operationId,
        array $newManifest,
        core_update_journal $journal
    ): array {
        if (!$this->verifyTemporary($operationId)) {
            return $this->recoveryFailure('core_restore_backup_invalid', 'The previous Core backup is not valid.');
        }

        return $this->restoreFrom($this->operationBackupRoot($operationId), $newManifest, $journal);
    }

    /**
     * Restore the single retained rollback.
     *
     * @param array<string, mixed> $currentManifest
     * @return array<string, mixed>
     */
    public function restoreRetained(array $currentManifest, core_update_journal $journal): array
    {
        $backupRoot = $this->stateDirectory . '/rollback';

        if (!$this->verifyAt($backupRoot)) {
            return $this->recoveryFailure('core_restore_backup_invalid', 'The retained Core rollback is not valid.');
        }

        return $this->restoreFrom($backupRoot, $currentManifest, $journal);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function retainedManifest(): ?array
    {
        $path = $this->stateDirectory . '/rollback/installed-manifest.json';

        if (!$this->verifyRollback() || !is_file($path)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        return is_array($manifest) ? $manifest : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function temporaryManifest(string $operationId): ?array
    {
        if (!$this->verifyTemporary($operationId)) {
            return null;
        }

        $path = $this->operationBackupRoot($operationId) . '/installed-manifest.json';
        $manifest = json_decode((string) file_get_contents($path), true);

        return is_array($manifest) ? $manifest : null;
    }

    /**
     * @param array<string, mixed> $newManifest
     * @return array<string, mixed>
     */
    private function restoreFrom(
        string $backupRoot,
        array $newManifest,
        core_update_journal $journal
    ): array {

        $newVersion = is_string($newManifest['version'] ?? null) ? $newManifest['version'] : '';
        $newPaths = $this->validateManifest($newManifest, $newVersion);

        if ($newPaths === null) {
            return $this->recoveryFailure('core_restore_manifest_invalid', 'The new Core manifest is invalid.');
        }

        $state = json_decode((string) file_get_contents($backupRoot . '/backup-state.json'), true);

        if (!is_array($state) || !is_array($state['files'] ?? null)) {
            return $this->recoveryFailure('core_restore_state_invalid', 'The previous Core backup state is invalid.');
        }

        $liveConfig = $this->installationRoot . '/app/core/config.php';

        if (
            !is_file($liveConfig) ||
            !hash_equals((string) ($state['config_sha256'] ?? ''), (string) hash_file('sha256', $liveConfig))
        ) {
            return $this->recoveryFailure('core_restore_config_changed', 'Protected Core configuration changed during the operation.');
        }

        if (!$journal->append('restore_started', ['installed_version' => $state['installed_version'] ?? null])) {
            return $this->recoveryFailure('core_journal_write_failed', 'The Core restoration journal could not be written.');
        }

        $oldPaths = [];

        foreach ($state['files'] as $file) {
            $path = is_array($file) ? ($file['path'] ?? null) : null;

            if (!is_string($path) || !$this->isSafeCorePath($path)) {
                return $this->recoveryFailure('core_restore_state_invalid', 'The previous Core backup state is invalid.');
            }

            $source = $backupRoot . '/files/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $destination = $this->installationPath($path);

            if (!$this->atomicReplace($source, $destination, (string) ($file['sha256'] ?? ''))) {
                return $this->recoveryFailure('core_restore_copy_failed', 'A previous Core file could not be restored.');
            }

            $oldPaths[$path] = true;

            if (!$journal->append('file_restored', ['path' => $path])) {
                return $this->recoveryFailure('core_journal_write_failed', 'The Core restoration journal could not be written.');
            }
        }

        foreach ($newPaths as $path) {
            if (isset($oldPaths[$path])) {
                continue;
            }

            $destination = $this->installationPath($path);

            if (is_file($destination) && !@unlink($destination)) {
                return $this->recoveryFailure('core_restore_remove_failed', 'A new Core file could not be removed during restoration.');
            }

            if (!$journal->append('new_file_removed', ['path' => $path])) {
                return $this->recoveryFailure('core_journal_write_failed', 'The Core restoration journal could not be written.');
            }
        }

        foreach ($state['files'] as $file) {
            $destination = $this->installationPath((string) $file['path']);

            if (
                !is_file($destination) ||
                !hash_equals((string) $file['sha256'], (string) hash_file('sha256', $destination))
            ) {
                return $this->recoveryFailure('core_restore_verify_failed', 'The restored Core could not be verified.');
            }
        }

        if (!$journal->append('restore_verified', ['installed_version' => $state['installed_version'] ?? null])) {
            return $this->recoveryFailure('core_journal_write_failed', 'The Core restoration journal could not be written.');
        }

        return [
            'success' => true,
            'outcome' => 'failed_restored',
            'phase' => 'restored',
            'installed_version' => $state['installed_version'] ?? null
        ];
    }

    private function verifyAt(string $backupRoot): bool
    {
        $statePath = $backupRoot . '/backup-state.json';

        $installedManifestPath = $backupRoot . '/installed-manifest.json';

        if (!is_file($statePath) || !is_file($installedManifestPath)) {
            return false;
        }

        $state = json_decode((string) file_get_contents($statePath), true);

        if (!is_array($state) || ($state['schema'] ?? null) !== 1 || !is_array($state['files'] ?? null)) {
            return false;
        }

        if (!hash_equals(
            (string) ($state['installed_manifest_sha256'] ?? ''),
            (string) hash_file('sha256', $installedManifestPath)
        )) {
            return false;
        }

        foreach ($state['files'] as $file) {
            if (!is_array($file) || !is_string($file['path'] ?? null) || !$this->isSafeCorePath($file['path'])) {
                return false;
            }

            $backupFile = $backupRoot . '/files/' . str_replace('/', DIRECTORY_SEPARATOR, $file['path']);

            if (
                !is_file($backupFile) || filesize($backupFile) !== ($file['size'] ?? null) ||
                !hash_equals((string) ($file['sha256'] ?? ''), (string) hash_file('sha256', $backupFile))
            ) {
                return false;
            }
        }

        $configBackup = $backupRoot . '/verification/config.php';

        return is_file($configBackup)
            && hash_equals((string) ($state['config_sha256'] ?? ''), (string) hash_file('sha256', $configBackup));
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<int, string>|null
     */
    private function validateManifest(array $manifest, string $version): ?array
    {
        if (($manifest['schema'] ?? null) !== 1 || ($manifest['version'] ?? null) !== $version || !is_array($manifest['files'] ?? null)) {
            return null;
        }

        $paths = [];

        foreach ($manifest['files'] as $file) {
            $path = is_array($file) ? ($file['path'] ?? null) : null;
            $hash = is_array($file) ? strtolower((string) ($file['sha256'] ?? '')) : '';
            $size = is_array($file) ? ($file['size'] ?? null) : null;

            if (
                !is_string($path) || !$this->isSafeCorePath($path) || isset($paths[$path]) ||
                !preg_match('/^[a-f0-9]{64}$/', $hash) || !is_int($size) || $size < 0
            ) {
                return null;
            }

            $paths[$path] = true;
        }

        foreach (['app/bootstrap.php', 'app/core/version.php'] as $requiredPath) {
            if (!isset($paths[$requiredPath])) {
                return null;
            }
        }

        return array_keys($paths);
    }

    private function isSafeCorePath(string $path): bool
    {
        $segments = explode('/', $path);

        if (
            $path === '' || str_contains($path, '\\') || str_starts_with($path, '/') ||
            str_contains($path, ':') || str_ends_with($path, '/') ||
            in_array('.', $segments, true) || in_array('..', $segments, true)
        ) {
            return false;
        }

        $normalized = strtolower($path);

        return $normalized !== 'app/core/config.php'
            && $normalized !== '.chaos-update'
            && !str_starts_with($normalized, '.chaos-update/')
            && $normalized !== '.chaos-core-manifest.json'
            && $normalized !== 'public'
            && !str_starts_with($normalized, 'public/');
    }

    private function installationPath(string $path): string
    {
        return $this->installationRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function operationBackupRoot(string $operationId): string
    {
        return $this->stateDirectory . '/operations/' . $operationId . '/backup';
    }

    private function atomicReplace(string $source, string $destination, string $expectedHash): bool
    {
        if (!is_file($source) || !preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
            return false;
        }

        $directory = dirname($destination);

        if (!is_dir($directory) && !@mkdir($directory, 0750, true)) {
            return false;
        }

        $temporary = $directory . '/.' . basename($destination) . '.restore.' . bin2hex(random_bytes(6));

        if (!@copy($source, $temporary) || !hash_equals($expectedHash, (string) hash_file('sha256', $temporary))) {
            @unlink($temporary);
            return false;
        }

        if (@rename($temporary, $destination)) {
            return true;
        }

        if (is_file($destination) && !@unlink($destination)) {
            @unlink($temporary);
            return false;
        }

        if (!@rename($temporary, $destination)) {
            @unlink($temporary);
            return false;
        }

        return true;
    }

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path) && !is_link($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(string $code, string $message): array
    {
        return [
            'success' => false,
            'outcome' => 'failed_unchanged',
            'phase' => 'backup',
            'error_code' => $code,
            'message' => $message
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recoveryFailure(string $code, string $message): array
    {
        return [
            'success' => false,
            'outcome' => 'failed_recovery_required',
            'phase' => 'restoring',
            'error_code' => $code,
            'message' => $message
        ];
    }
}
/* [End AI:GPT-5.6 Sol] */
