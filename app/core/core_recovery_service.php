<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_recovery_service
{
    private string $installationRoot;
    private string $stateDirectory;

    public function __construct(string $installationRoot, string $stateDirectory)
    {
        $this->installationRoot = rtrim($installationRoot, '/\\');
        $this->stateDirectory = rtrim($stateDirectory, '/\\');
    }

    /**
     * Recover an interrupted operation, otherwise restore the retained rollback.
     *
     * @return array<string, mixed>
     */
    public function recover(): array
    {
        $currentManifest = $this->readManifest($this->installationRoot . '/.chaos-core-manifest.json');

        if ($currentManifest === null) {
            return $this->failure('core_recovery_manifest_missing', 'Installed Core ownership state is unavailable.');
        }

        $lock = new core_update_lock($this->stateDirectory);
        $lockState = $lock->read();
        $backups = new core_backup_manager($this->installationRoot, $this->stateDirectory);
        $maintenance = new core_maintenance($this->stateDirectory);

        if (is_array($lockState) && is_string($lockState['operation_id'] ?? null)) {
            $operationId = $lockState['operation_id'];
            $journal = new core_update_journal($this->stateDirectory, $operationId);
            $newManifest = $this->activeTargetManifest($journal);
            $previousManifest = $backups->temporaryManifest($operationId);

            if ($newManifest === null || $previousManifest === null) {
                return $this->failure('core_recovery_state_invalid', 'Active Core recovery state is incomplete.');
            }

            $maintenance->activate($operationId, 'restoring');
            $result = $backups->restoreTemporary($operationId, $newManifest, $journal);

            if (($result['success'] ?? false) === true && $this->writeInstalledManifest($previousManifest)) {
                $maintenance->deactivate($operationId);
                $lock->release($operationId);
                return $result + ['operation_id' => $operationId];
            }

            return $result + ['operation_id' => $operationId];
        }

        $previousManifest = $backups->retainedManifest();

        if ($previousManifest === null) {
            return $this->failure('core_rollback_missing', 'No verified previous Core rollback is available.');
        }

        if (($previousManifest['version'] ?? null) === ($currentManifest['version'] ?? null)) {
            return $this->failure('core_rollback_already_installed', 'The retained Core version is already installed.');
        }

        $lockResult = $lock->acquire(
            (string) ($currentManifest['version'] ?? ''),
            (string) ($previousManifest['version'] ?? '')
        );

        if (($lockResult['success'] ?? false) !== true) {
            return $this->failure('core_update_in_progress', 'Another Core operation is in progress.');
        }

        $operationId = (string) $lockResult['state']['operation_id'];
        $journal = new core_update_journal($this->stateDirectory, $operationId);
        $journal->append('manual_rollback_started', [
            'from_version' => $currentManifest['version'] ?? null,
            'to_version' => $previousManifest['version'] ?? null
        ]);
        $maintenance->activate($operationId, 'restoring');
        $result = $backups->restoreRetained($currentManifest, $journal);

        if (($result['success'] ?? false) === true && $this->writeInstalledManifest($previousManifest)) {
            $journal->append('manual_rollback_complete', ['installed_version' => $previousManifest['version'] ?? null]);
            $maintenance->deactivate($operationId);
            $lock->release($operationId);

            return $result + ['operation_id' => $operationId];
        }

        return $result + ['operation_id' => $operationId];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeTargetManifest(core_update_journal $journal): ?array
    {
        foreach ($journal->read() as $entry) {
            if (($entry['event'] ?? null) !== 'update_started') {
                continue;
            }

            $stagingId = $entry['data']['staging_id'] ?? null;

            if (!is_string($stagingId) || !preg_match('/^[a-f0-9]{32}$/', $stagingId)) {
                return null;
            }

            return $this->readManifest(
                $this->stateDirectory . '/staging/' . $stagingId . '/files/core-manifest.json'
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifest(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        return is_array($manifest) ? $manifest : null;
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
    private function failure(string $code, string $message): array
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
