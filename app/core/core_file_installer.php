<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_file_installer
{
    private string $installationRoot;
    private core_update_lock $lock;
    private core_backup_manager $backups;
    private $faultInjector;

    public function __construct(
        string $installationRoot,
        core_update_lock $lock,
        core_backup_manager $backups,
        ?callable $faultInjector = null
    ) {
        $this->installationRoot = rtrim($installationRoot, '/\\');
        $this->lock = $lock;
        $this->backups = $backups;
        $this->faultInjector = $faultInjector;
    }

    /**
     * Install all staged Core files except the final version marker.
     *
     * @param array<string, mixed> $oldManifest
     * @param array<string, mixed> $newManifest
     * @return array<string, mixed>
     */
    public function installFiles(
        string $operationId,
        string $stagedFilesRoot,
        array $oldManifest,
        array $newManifest,
        core_update_journal $journal
    ): array {
        if (!$this->ownsLock($operationId)) {
            return $this->unchangedFailure('core_install_lock_invalid', 'The Core update lock is not owned by this operation.');
        }

        if (!$this->backups->verifyTemporary($operationId)) {
            return $this->unchangedFailure('core_install_backup_invalid', 'The previous Core backup is not valid.');
        }

        if (!$this->backups->protectedConfigurationUnchanged($operationId)) {
            return $this->unchangedFailure('core_install_config_changed', 'Protected Core configuration changed after backup.');
        }

        $oldFiles = $this->manifestFileMap($oldManifest);
        $newFiles = $this->manifestFileMap($newManifest);
        $stagedManifestPath = rtrim($stagedFilesRoot, '/\\') . '/core-manifest.json';
        $stagedManifest = is_file($stagedManifestPath)
            ? json_decode((string) file_get_contents($stagedManifestPath), true)
            : null;

        if ($oldFiles === null || $newFiles === null || !is_array($stagedManifest) || $stagedManifest != $newManifest) {
            return $this->unchangedFailure('core_install_manifest_invalid', 'Core installation manifests are invalid.');
        }

        if (!$journal->append('filesystem_install_started', ['target_version' => $newManifest['version']])) {
            return $this->unchangedFailure('core_journal_write_failed', 'The Core installation journal could not be written.');
        }

        try {
            foreach ($newFiles as $path => $file) {
                if ($path === 'app/core/version.php') {
                    continue;
                }

                $this->injectFault('before_replace', $path);
                $source = rtrim($stagedFilesRoot, '/\\') . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
                $destination = $this->installationPath($path);

                if (!$this->atomicReplace($source, $destination, $file['sha256'])) {
                    throw new RuntimeException('core_install_replace_failed|A Core file could not be installed.');
                }

                if (!$journal->append('file_installed', ['path' => $path])) {
                    throw new RuntimeException('core_journal_write_failed|The Core installation journal could not be written.');
                }

                $this->injectFault('after_replace', $path);
            }

            foreach ($oldFiles as $path => $file) {
                if (isset($newFiles[$path])) {
                    continue;
                }

                $this->injectFault('before_remove', $path);
                $destination = $this->installationPath($path);

                if (is_file($destination) && !@unlink($destination)) {
                    throw new RuntimeException('core_install_remove_failed|An obsolete Core file could not be removed.');
                }

                if (!$journal->append('obsolete_file_removed', ['path' => $path])) {
                    throw new RuntimeException('core_journal_write_failed|The Core installation journal could not be written.');
                }
            }

            if (!$journal->append('filesystem_install_complete', ['version_pending' => true])) {
                throw new RuntimeException('core_journal_write_failed|The Core installation journal could not be written.');
            }

            return [
                'success' => true,
                'outcome' => 'files_installed',
                'phase' => 'installing',
                'target_version' => $newManifest['version'],
                'version_pending' => true
            ];
        } catch (Throwable $exception) {
            [$code, $message] = array_pad(explode('|', $exception->getMessage(), 2), 2, 'Core file installation failed.');
            $journal->append('filesystem_install_failed', ['error_code' => $code]);
            $restored = $this->backups->restoreTemporary($operationId, $newManifest, $journal);

            if (($restored['success'] ?? false) === true) {
                $restored['error_code'] = $code;
                $restored['message'] = $message;
            }

            return $restored;
        }
    }

    /**
     * Commit app/core/version.php only after all other verification succeeds.
     *
     * @param array<string, mixed> $newManifest
     * @return array<string, mixed>
     */
    public function commitVersion(
        string $operationId,
        string $stagedFilesRoot,
        array $newManifest,
        core_update_journal $journal
    ): array {
        if (!$this->ownsLock($operationId) || !$this->backups->verifyTemporary($operationId)) {
            return $this->recoveryFailure('core_version_commit_precondition_failed', 'Core version commit preconditions failed.');
        }

        $files = $this->manifestFileMap($newManifest);
        $versionPath = 'app/core/version.php';

        if ($files === null || !isset($files[$versionPath])) {
            return $this->recoveryFailure('core_version_manifest_invalid', 'The Core version file is absent from the manifest.');
        }

        $source = rtrim($stagedFilesRoot, '/\\') . '/' . str_replace('/', DIRECTORY_SEPARATOR, $versionPath);
        $destination = $this->installationPath($versionPath);

        if (!$this->atomicReplace($source, $destination, $files[$versionPath]['sha256'])) {
            $journal->append('version_commit_failed');
            $restored = $this->backups->restoreTemporary($operationId, $newManifest, $journal);

            return ($restored['success'] ?? false) === true
                ? $restored + ['error_code' => 'core_version_commit_failed']
                : $restored;
        }

        if (!$journal->append('version_committed', ['version' => $newManifest['version']])) {
            return $this->backups->restoreTemporary($operationId, $newManifest, $journal);
        }

        return [
            'success' => true,
            'outcome' => 'version_committed',
            'phase' => 'verifying',
            'target_version' => $newManifest['version']
        ];
    }

    private function ownsLock(string $operationId): bool
    {
        $state = $this->lock->read();

        return is_array($state) && ($state['operation_id'] ?? null) === $operationId;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, array{sha256: string, size: int}>|null
     */
    private function manifestFileMap(array $manifest): ?array
    {
        if (
            ($manifest['schema'] ?? null) !== 1 ||
            !is_string($manifest['version'] ?? null) ||
            !preg_match('/^\d+\.\d+\.\d+$/', $manifest['version']) ||
            !is_array($manifest['files'] ?? null)
        ) {
            return null;
        }

        $map = [];

        foreach ($manifest['files'] as $file) {
            $path = is_array($file) ? ($file['path'] ?? null) : null;
            $hash = is_array($file) ? strtolower((string) ($file['sha256'] ?? '')) : '';
            $size = is_array($file) ? ($file['size'] ?? null) : null;

            if (
                !is_string($path) || !$this->isSafeCorePath($path) || isset($map[$path]) ||
                !preg_match('/^[a-f0-9]{64}$/', $hash) || !is_int($size) || $size < 0
            ) {
                return null;
            }

            $map[$path] = ['sha256' => $hash, 'size' => $size];
        }

        foreach (['app/bootstrap.php', 'app/core/version.php'] as $requiredPath) {
            if (!isset($map[$requiredPath])) {
                return null;
            }
        }

        return $map;
    }

    private function atomicReplace(string $source, string $destination, string $expectedHash): bool
    {
        if (!is_file($source) || !hash_equals($expectedHash, (string) hash_file('sha256', $source))) {
            return false;
        }

        $directory = dirname($destination);

        if (!is_dir($directory) && !@mkdir($directory, 0750, true)) {
            return false;
        }

        $temporary = $directory . '/.' . basename($destination) . '.install.' . bin2hex(random_bytes(6));

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

    private function installationPath(string $path): string
    {
        return $this->installationRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
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

    private function injectFault(string $phase, string $path): void
    {
        if (is_callable($this->faultInjector) && ($this->faultInjector)($phase, $path) === true) {
            throw new RuntimeException('core_test_fault|Injected Core installation failure.');
        }
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

    /**
     * @return array<string, mixed>
     */
    private function recoveryFailure(string $code, string $message): array
    {
        return [
            'success' => false,
            'outcome' => 'failed_recovery_required',
            'phase' => 'installing',
            'error_code' => $code,
            'message' => $message
        ];
    }
}
/* [End AI:GPT-5.6 Sol] */
