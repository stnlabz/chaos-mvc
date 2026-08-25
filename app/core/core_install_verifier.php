<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_install_verifier
{
    private string $installationRoot;
    private core_backup_manager $backups;
    private core_migration_database $database;
    private $syntaxChecker;
    private $bootstrapChecker;
    private $versionChecker;

    public function __construct(
        string $installationRoot,
        core_backup_manager $backups,
        core_migration_database $database,
        ?callable $syntaxChecker = null,
        ?callable $bootstrapChecker = null,
        ?callable $versionChecker = null
    ) {
        $this->installationRoot = rtrim($installationRoot, '/\\');
        $this->backups = $backups;
        $this->database = $database;
        $this->syntaxChecker = $syntaxChecker;
        $this->bootstrapChecker = $bootstrapChecker;
        $this->versionChecker = $versionChecker;
    }

    /**
     * Verify the new Core before app/core/version.php is committed.
     *
     * @param array<string, mixed> $oldManifest
     * @param array<string, mixed> $newManifest
     * @return array<string, mixed>
     */
    public function preCommit(
        string $operationId,
        string $stagedFilesRoot,
        array $oldManifest,
        array $newManifest,
        core_update_journal $journal
    ): array {
        $oldFiles = $this->fileMap($oldManifest);
        $newFiles = $this->fileMap($newManifest);

        if ($oldFiles === null || $newFiles === null || !$this->backups->protectedConfigurationUnchanged($operationId)) {
            return $this->failure('core_verify_precondition_failed', 'Core verification preconditions failed.');
        }

        foreach ($newFiles as $path => $file) {
            $candidate = $path === 'app/core/version.php'
                ? rtrim($stagedFilesRoot, '/\\') . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path)
                : $this->installationPath($path);

            if (!$this->verifyFile($candidate, $file)) {
                return $this->failure('core_verify_file_failed', 'A new Core file failed verification.');
            }

            if (strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) === 'php' && !$this->checkSyntax($candidate)) {
                return $this->failure('core_verify_syntax_failed', 'A new Core PHP file failed syntax validation.');
            }
        }

        foreach ($oldFiles as $path => $file) {
            if (!isset($newFiles[$path]) && is_file($this->installationPath($path))) {
                return $this->failure('core_verify_obsolete_file', 'An obsolete Core file remains installed.');
            }
        }

        if (!$journal->verify() || $this->journalTouchesPublic($journal)) {
            return $this->failure('core_verify_journal_failed', 'The Core operation journal is invalid.');
        }

        foreach ($newManifest['migrations'] ?? [] as $migration) {
            if (!is_array($migration)) {
                return $this->failure('core_verify_migration_failed', 'Core migration state is invalid.');
            }

            $id = (string) ($migration['id'] ?? '');
            $checksum = (string) ($migration['sha256'] ?? '');

            if (!hash_equals($checksum, (string) $this->database->appliedChecksum($id))) {
                return $this->failure('core_verify_migration_failed', 'A required Core migration is not applied.');
            }
        }

        try {
            if (!$this->database->healthCheck()) {
                return $this->failure('core_verify_database_failed', 'Core database health verification failed.');
            }
        } catch (Throwable $exception) {
            return $this->failure('core_verify_database_failed', 'Core database health verification failed.');
        }

        if (!$this->checkBootstrap()) {
            return $this->failure('core_verify_bootstrap_failed', 'Core bootstrap health verification failed.');
        }

        if (!$journal->append('precommit_verification_passed', ['version' => $newManifest['version']])) {
            return $this->failure('core_journal_write_failed', 'The Core verification journal could not be written.');
        }

        return [
            'success' => true,
            'outcome' => 'precommit_verified',
            'phase' => 'verifying',
            'target_version' => $newManifest['version']
        ];
    }

    /**
     * Verify the complete live Core after final version commit.
     *
     * @param array<string, mixed> $newManifest
     * @return array<string, mixed>
     */
    public function postCommit(array $newManifest, core_update_journal $journal): array
    {
        $files = $this->fileMap($newManifest);

        if ($files === null) {
            return $this->failure('core_verify_manifest_failed', 'The installed Core manifest is invalid.');
        }

        foreach ($files as $path => $file) {
            if (!$this->verifyFile($this->installationPath($path), $file)) {
                return $this->failure('core_verify_file_failed', 'An installed Core file failed final verification.');
            }
        }

        if (!$this->checkVersion((string) $newManifest['version'])) {
            return $this->failure('core_verify_version_failed', 'The installed Core version is incorrect.');
        }

        if (!$journal->append('postcommit_verification_passed', ['version' => $newManifest['version']])) {
            return $this->failure('core_journal_write_failed', 'The Core verification journal could not be written.');
        }

        return [
            'success' => true,
            'outcome' => 'install_verified',
            'phase' => 'verified',
            'installed_version' => $newManifest['version']
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, array{sha256: string, size: int}>|null
     */
    private function fileMap(array $manifest): ?array
    {
        if (!is_array($manifest['files'] ?? null) || !is_string($manifest['version'] ?? null)) {
            return null;
        }

        $map = [];

        foreach ($manifest['files'] as $file) {
            $path = is_array($file) ? ($file['path'] ?? null) : null;
            $hash = is_array($file) ? strtolower((string) ($file['sha256'] ?? '')) : '';
            $size = is_array($file) ? ($file['size'] ?? null) : null;

            if (
                !is_string($path) || isset($map[$path]) || !$this->safePath($path) ||
                !preg_match('/^[a-f0-9]{64}$/', $hash) || !is_int($size) || $size < 0
            ) {
                return null;
            }

            $map[$path] = ['sha256' => $hash, 'size' => $size];
        }

        return isset($map['app/bootstrap.php'], $map['app/core/version.php']) ? $map : null;
    }

    /**
     * @param array{sha256: string, size: int} $file
     */
    private function verifyFile(string $path, array $file): bool
    {
        return is_file($path)
            && filesize($path) === $file['size']
            && hash_equals($file['sha256'], (string) hash_file('sha256', $path));
    }

    private function checkSyntax(string $path): bool
    {
        if (is_callable($this->syntaxChecker)) {
            return ($this->syntaxChecker)($path) === true;
        }

        $process = @proc_open(
            [PHP_BINARY, '-l', $path],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if (!is_resource($process)) {
            return false;
        }

        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }

    private function checkBootstrap(): bool
    {
        if (is_callable($this->bootstrapChecker)) {
            return ($this->bootstrapChecker)($this->installationRoot) === true;
        }

        $script = 'putenv("CHAOS_CORE_HEALTH_CHECK=1");'
            . 'require $argv[1]."/app/bootstrap.php";'
            . 'exit(class_exists("router")&&class_exists("controller")&&class_exists("model")?0:1);';
        $process = @proc_open(
            [PHP_BINARY, '-r', $script, $this->installationRoot],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if (!is_resource($process)) {
            return false;
        }

        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }

    private function checkVersion(string $expectedVersion): bool
    {
        if (is_callable($this->versionChecker)) {
            return ($this->versionChecker)($this->installationRoot, $expectedVersion) === true;
        }

        $script = 'require $argv[1]."/app/core/version.php";'
            . 'exit(defined("CHAOS_VERSION")&&CHAOS_VERSION===$argv[2]?0:1);';
        $process = @proc_open(
            [PHP_BINARY, '-r', $script, $this->installationRoot, $expectedVersion],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if (!is_resource($process)) {
            return false;
        }

        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }

    private function journalTouchesPublic(core_update_journal $journal): bool
    {
        foreach ($journal->read() as $entry) {
            $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
            $path = strtolower((string) ($data['path'] ?? ''));

            if ($path === 'public' || str_starts_with($path, 'public/')) {
                return true;
            }
        }

        return false;
    }

    private function safePath(string $path): bool
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
            && $normalized !== 'public'
            && !str_starts_with($normalized, 'public/')
            && $normalized !== '.chaos-update'
            && !str_starts_with($normalized, '.chaos-update/');
    }

    private function installationPath(string $path): string
    {
        return $this->installationRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(string $code, string $message): array
    {
        return [
            'success' => false,
            'outcome' => 'verification_failed',
            'phase' => 'verifying',
            'error_code' => $code,
            'message' => $message
        ];
    }
}
/* [End AI:GPT-5.6 Sol] */
