<?php

/**
 * Chaos MVC Core Updater
 *
 * Performs deterministic Chaos MVC Core updates from the authoritative
 * Chaos MVC release service.
 *
 * Path: /app/lib/updater.php
 */

/* [AI:GPT-5.6 Sol | 2026-08-25 20:15:00 UTC] */
class updater_engine
{
    /**
     * Authoritative Chaos MVC release manifest.
     */
    private const MANIFEST_URL =
        'https://www.chaos-mvc.org/updates/current.json';

    /**
     * Runtime updater directory.
     *
     * @var string
     */
    private string $runtimeDir;

    /**
     * Temporary update directory.
     *
     * @var string
     */
    private string $tempDir;

    /**
     * Backup directory.
     *
     * @var string
     */
    private string $backupRoot;

    /**
     * Status file.
     *
     * @var string
     */
    private string $statusFile;

    /**
     * Maintenance marker.
     *
     * @var string
     */
    private string $maintenanceFile;

    /**
     * Initialize updater paths.
     */
    public function __construct()
    {
        $root = dirname(APPROOT);

        $this->runtimeDir =
            APPROOT . '/data/updater';

        $this->tempDir =
            $root . '/tmp/updater';

        $this->backupRoot =
            $root . '/backups/updater';

        $this->statusFile =
            $this->runtimeDir . '/status.json';

        $this->maintenanceFile =
            $this->runtimeDir . '/maintenance.lock';
    }

    /**
     * Get the currently installed Chaos MVC version.
     *
     * @return string
     */
    public function getCurrentVersion(): string
    {
        require_once APPROOT . '/core/version.php';

        return defined('CHAOS_VERSION')
            ? (string) CHAOS_VERSION
            : '0.0.0';
    }

    /**
     * Check the authoritative release manifest.
     *
     * @return array
     */
    public function checkForUpdate(): array
    {
        $current = $this->getCurrentVersion();

        try {
            $manifest = $this->fetchJson(
                self::MANIFEST_URL
            );

            $this->validateReleaseIdentity(
                $manifest,
                $current
            );

            $target = trim(
                (string) ($manifest['version'] ?? '')
            );

            if ($target === '') {
                throw new RuntimeException(
                    'Release manifest does not contain a version.'
                );
            }

            $available = version_compare(
                $target,
                $current,
                '>'
            );

            return [
                'success' => true,
                'available' => $available,
                'current_version' => $current,
                'target_version' => $target,
                'manifest' => $manifest
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'available' => false,
                'current_version' => $current,
                'target_version' => null,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Return current update status.
     *
     * @return array
     */
    public function getStatus(): array
    {
        if (!is_file($this->statusFile)) {
            return [
                'status' => 'idle',
                'stage' => 'Idle',
                'percent' => 0,
                'current_version' => $this->getCurrentVersion(),
                'target_version' => null,
                'message' => 'No update is currently running.',
                'error' => null
            ];
        }

        $raw = file_get_contents(
            $this->statusFile
        );

        if ($raw === false) {
            return [
                'status' => 'unknown',
                'stage' => 'Unknown',
                'percent' => 0,
                'message' => 'Updater status could not be read.',
                'error' => null
            ];
        }

        $status = json_decode(
            $raw,
            true
        );

        return is_array($status)
            ? $status
            : [
                'status' => 'unknown',
                'stage' => 'Unknown',
                'percent' => 0,
                'message' => 'Updater status is invalid.',
                'error' => null
            ];
    }

    /**
     * Execute the Core update lifecycle.
     *
     * @return array
     */
    public function run(): array
    {
        $current = $this->getCurrentVersion();

        $this->ensureDirectory(
            $this->runtimeDir
        );

        $this->ensureDirectory(
            $this->tempDir
        );

        $this->ensureDirectory(
            $this->backupRoot
        );

        $this->setStatus(
            'running',
            'Checking update',
            5,
            $current,
            null,
            'Checking authoritative release manifest.'
        );

        $manifest = $this->fetchJson(
            self::MANIFEST_URL
        );

        $this->validateReleaseIdentity(
            $manifest,
            $current
        );

        $target = (string) $manifest['version'];

        if (!version_compare($target, $current, '>')) {
            $this->setStatus(
                'complete',
                'Up to date',
                100,
                $current,
                $current,
                'Chaos MVC is already up to date.'
            );

            return [
                'success' => true,
                'version' => $current,
                'message' => 'Already up to date.'
            ];
        }

        $this->validateManifest(
            $manifest,
            $current
        );

        $packageFile =
            $this->tempDir
            . '/chaos-mvc-'
            . $target
            . '.zip';

        $stageDir =
            $this->tempDir
            . '/stage';

        $backupDir =
            $this->backupRoot
            . '/'
            . $current;

        $filesManifest = [];

        try {
            /*
             * DOWNLOAD
             */
            $this->setStatus(
                'running',
                'Downloading',
                20,
                $current,
                $target,
                'Downloading Chaos MVC ' . $target . '.'
            );

            $this->downloadFile(
                (string) $manifest['package'],
                $packageFile
            );

            /*
             * PACKAGE VERIFY
             */
            $this->setStatus(
                'running',
                'Verifying package',
                30,
                $current,
                $target,
                'Verifying release package SHA-256.'
            );

            $this->verifyFileHash(
                $packageFile,
                (string) $manifest['sha256']
            );

            /*
             * FILE MANIFEST
             */
            $filesManifest = $this->fetchJson(
                (string) $manifest['files_manifest']
            );

            /*
             * The release manifest is validated before the archive
             * is staged or maintenance mode begins.
             */
            $this->validateFilesManifest(
                $filesManifest,
                $target
            );

            /*
             * STAGE
             */
            $this->setStatus(
                'running',
                'Staging',
                40,
                $current,
                $target,
                'Preparing release files.'
            );

            $this->removeDirectory(
                $stageDir
            );

            $this->extractPackage(
                $packageFile,
                $stageDir
            );

            /*
             * Verify staged files BEFORE taking the site offline.
             */
            $this->verifyStagedFiles(
                $stageDir,
                $filesManifest
            );

            /*
             * MAINTENANCE
             */
            $this->setStatus(
                'running',
                'Entering maintenance',
                50,
                $current,
                $target,
                'Temporarily placing public access into maintenance mode.'
            );

            $this->enterMaintenance(
                $target
            );

            /*
             * BACKUP
             */
            $this->setStatus(
                'running',
                'Backing up Core',
                60,
                $current,
                $target,
                'Backing up the currently installed Core.'
            );

            $this->removeDirectory(
                $backupDir
            );

            $this->backupFiles(
                $backupDir,
                $filesManifest
            );

            /*
             * APPLY
             */
            $this->setStatus(
                'running',
                'Installing Core',
                75,
                $current,
                $target,
                'Installing Chaos MVC ' . $target . '.'
            );

            $this->applyFiles(
                $stageDir,
                $filesManifest
            );

            /*
             * VERIFY INSTALLED CORE
             */
            $this->setStatus(
                'running',
                'Verifying installation',
                88,
                $current,
                $target,
                'Verifying installed Core files.'
            );

            $this->verifyInstalledFiles(
                $filesManifest
            );

            /*
             * OPTIONAL DATABASE MIGRATION
             */
            if (!empty($manifest['migration'])) {
                $this->setStatus(
                    'running',
                    'Updating database',
                    93,
                    $current,
                    $target,
                    'Applying required database migration.'
                );

                $this->runMigration(
                    $stageDir,
                    (string) $manifest['migration']
                );
            }

            /*
             * FINAL FILE VERIFICATION
             */
            $this->verifyInstalledFiles(
                $filesManifest
            );

            /*
             * SUCCESS
             */
            $this->setStatus(
                'running',
                'Cleaning up',
                98,
                $current,
                $target,
                'Cleaning temporary update files.'
            );

            $this->removeDirectory(
                $stageDir
            );

            if (is_file($packageFile)) {
                unlink($packageFile);
            }

            /*
             * Backup is no longer required after successful verification.
             */
            $this->removeDirectory(
                $backupDir
            );

            $this->leaveMaintenance();

            $this->setStatus(
                'complete',
                'Complete',
                100,
                $target,
                $target,
                'Chaos MVC updated successfully to ' . $target . '.'
            );

            return [
                'success' => true,
                'version' => $target,
                'message' => 'Update complete.'
            ];
        } catch (Throwable $e) {
            /*
             * If backup exists, attempt deterministic rollback.
             */
            if (is_dir($backupDir)) {
                $this->setStatus(
                    'rollback',
                    'Rolling back',
                    95,
                    $current,
                    $target ?? null,
                    'Update failed. Restoring previous Core.',
                    $e->getMessage()
                );

                try {
                    $this->restoreBackup(
                        $backupDir
                    );

                    $this->leaveMaintenance();

                    $this->setStatus(
                        'failed',
                        'Rollback complete',
                        100,
                        $current,
                        $target ?? null,
                        'Update failed. Previous Core restored.',
                        $e->getMessage()
                    );
                } catch (Throwable $rollbackError) {
                    $this->setStatus(
                        'failed',
                        'Rollback failed',
                        100,
                        $current,
                        $target ?? null,
                        'Automatic rollback failed.',
                        $rollbackError->getMessage()
                    );

                    /*
                     * Maintenance intentionally remains active if rollback
                     * itself fails.
                     */
                }
            } else {
                $this->leaveMaintenance();

                $this->setStatus(
                    'failed',
                    'Update failed',
                    100,
                    $current,
                    $target ?? null,
                    'Update stopped before Core replacement.',
                    $e->getMessage()
                );
            }

            $this->removeDirectory(
                $stageDir
            );

            if (is_file($packageFile)) {
                unlink($packageFile);
            }

            return [
                'success' => false,
                'version' => $current,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate the primary release manifest.
     *
     * @param array $manifest Manifest.
     * @param string $current Current version.
     * @return void
     */
    private function validateManifest(
        array $manifest,
        string $current
    ): void {
        $this->validateReleaseIdentity(
            $manifest,
            $current
        );

        $required = [
            'package',
            'sha256',
            'files_manifest'
        ];

        foreach ($required as $field) {
            if (
                !isset($manifest[$field])
                || trim((string) $manifest[$field]) === ''
            ) {
                throw new RuntimeException(
                    'Release manifest is missing ' . $field . '.'
                );
            }
        }

        $this->requireHttpsUrl(
            (string) $manifest['package']
        );

        $this->requireHttpsUrl(
            (string) $manifest['files_manifest']
        );

        if (
            !preg_match(
                '/^[a-f0-9]{64}$/i',
                (string) $manifest['sha256']
            )
        ) {
            throw new RuntimeException(
                'Release package SHA-256 is invalid.'
            );
        }
    }

    /**
     * Validate release identity and compatibility.
     *
     * @param array $manifest Release manifest.
     * @param string $current Current version.
     * @return void
     */
    private function validateReleaseIdentity(
        array $manifest,
        string $current
    ): void {
        foreach (
            [
                'product',
                'version',
                'minimum_version'
            ] as $field
        ) {
            if (
                !isset($manifest[$field])
                || trim((string) $manifest[$field]) === ''
            ) {
                throw new RuntimeException(
                    'Release manifest is missing ' . $field . '.'
                );
            }
        }

        if ($manifest['product'] !== 'Chaos MVC') {
            throw new RuntimeException(
                'Release manifest product does not match Chaos MVC.'
            );
        }

        if (
            version_compare(
                $current,
                (string) $manifest['minimum_version'],
                '<'
            )
        ) {
            throw new RuntimeException(
                'This installation is too old for the available update.'
            );
        }
    }

    /**
     * Validate the files manifest.
     *
     * Installation-owned paths are outside Core update authority.
     *
     * @param array $manifest Files manifest.
     * @param string $target Target release.
     * @return void
     */
    private function validateFilesManifest(
        array $manifest,
        string $target
    ): void {
        if (
            ($manifest['product'] ?? null) !== 'Chaos MVC'
        ) {
            throw new RuntimeException(
                'Files manifest product is invalid.'
            );
        }

        if (
            (string) ($manifest['version'] ?? '') !== $target
        ) {
            throw new RuntimeException(
                'Files manifest version does not match the release.'
            );
        }

        if (
            empty($manifest['core'])
            || !is_array($manifest['core'])
        ) {
            throw new RuntimeException(
                'Files manifest does not define the Core.'
            );
        }

        foreach (
            $this->getManifestFiles($manifest)
            as $path => $hash
        ) {
            $this->validateRelativePath(
                (string) $path
            );

            if (
                $this->isInstallationOwnedPath(
                    (string) $path
                )
            ) {
                throw new RuntimeException(
                    'Release attempts to modify installation-owned content: '
                    . $path
                    . '.'
                );
            }

            if (
                !preg_match(
                    '/^[a-f0-9]{64}$/i',
                    (string) $hash
                )
            ) {
                throw new RuntimeException(
                    'Invalid SHA-256 for ' . $path . '.'
                );
            }
        }
    }

    /**
     * Return all authoritative release files.
     *
     * Public files may be included only when explicitly declared by a
     * release and must still remain outside installation-owned paths.
     *
     * @param array $manifest Files manifest.
     * @return array
     */
    private function getManifestFiles(
        array $manifest
    ): array {
        $core = $manifest['core'] ?? [];
        $public = $manifest['public'] ?? [];

        if (!is_array($core)) {
            $core = [];
        }

        if (!is_array($public)) {
            $public = [];
        }

        $files = array_merge(
            $core,
            $public
        );

        /*
         * Defense in depth: any code path consuming manifest files
         * receives the same installation-ownership protection.
         */
        /* [AI:GPT-5.6 Sol | 2026-08-25 22:21:00 UTC] */
        foreach ($files as $path => $hash) {
            if (
                $this->isInstallationOwnedPath(
                    (string) $path
                )
            ) {
                throw new RuntimeException(
                    'Release attempts to modify installation-owned content: '
                    . $path
                    . '.'
                );
            }
        }
        /* [End AI:GPT-5.6 Sol] */

        return $files;
    }

    /**
     * Determine whether a release path belongs to the installation.
     *
     * These paths contain site content, installation configuration,
     * credentials, updater runtime state, or uploaded content and are
     * never writable by a Chaos MVC Core release.
     *
     * @param string $path Release-relative path.
     * @return bool
     */
    /* [AI:GPT-5.6 Sol | 2026-08-25 22:21:00 UTC] */
    private function isInstallationOwnedPath(
        string $path
    ): bool {
        $path = str_replace(
            '\\',
            '/',
            trim($path)
        );

        $segments = [];

        foreach (
            explode(
                '/',
                trim($path, '/')
            ) as $segment
        ) {
            if (
                $segment === ''
                || $segment === '.'
            ) {
                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode(
            '/',
            $segments
        );

        if (
            $normalized === 'app/views/public'
            || str_starts_with(
                $normalized,
                'app/views/public/'
            )
        ) {
            return true;
        }

        if (
            $normalized === 'app/data/updater'
            || str_starts_with(
                $normalized,
                'app/data/updater/'
            )
        ) {
            return true;
        }

        if (
            $normalized === 'public/uploads'
            || str_starts_with(
                $normalized,
                'public/uploads/'
            )
        ) {
            return true;
        }

        return in_array(
            $normalized,
            [
                'app/core/config.php',
                'app/data/site.json',
                'app/data/mailer.json'
            ],
            true
        );
    }
    /* [End AI:GPT-5.6 Sol] */

    /**
     * Verify staged release files.
     *
     * @param string $stageDir Stage directory.
     * @param array $manifest Files manifest.
     * @return void
     */
    private function verifyStagedFiles(
        string $stageDir,
        array $manifest
    ): void {
        foreach (
            $this->getManifestFiles($manifest)
            as $path => $hash
        ) {
            $file = $stageDir . '/' . $path;

            if (!is_file($file)) {
                throw new RuntimeException(
                    'Release package is missing ' . $path . '.'
                );
            }

            $this->verifyFileHash(
                $file,
                (string) $hash
            );
        }
    }

    /**
     * Backup every release-controlled destination file.
     *
     * @param string $backupDir Backup directory.
     * @param array $manifest Files manifest.
     * @return void
     */
    private function backupFiles(
        string $backupDir,
        array $manifest
    ): void {
        $this->ensureDirectory(
            $backupDir
        );

        $state = [];

        foreach (
            $this->getManifestFiles($manifest)
            as $path => $hash
        ) {
            $source =
                dirname(APPROOT)
                . '/'
                . $path;

            $destination =
                $backupDir
                . '/files/'
                . $path;

            $state[$path] = is_file($source);

            if (!$state[$path]) {
                continue;
            }

            $this->ensureDirectory(
                dirname($destination)
            );

            if (!copy($source, $destination)) {
                throw new RuntimeException(
                    'Could not back up ' . $path . '.'
                );
            }
        }

        $encoded = json_encode(
            $state,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
        );

        if (
            $encoded === false
            || file_put_contents(
                $backupDir . '/backup.json',
                $encoded . PHP_EOL,
                LOCK_EX
            ) === false
        ) {
            throw new RuntimeException(
                'Could not write backup state.'
            );
        }
    }

    /**
     * Apply all authoritative release files.
     *
     * @param string $stageDir Stage directory.
     * @param array $manifest Files manifest.
     * @return void
     */
    private function applyFiles(
        string $stageDir,
        array $manifest
    ): void {
        foreach (
            $this->getManifestFiles($manifest)
            as $path => $hash
        ) {
            $source =
                $stageDir
                . '/'
                . $path;

            $destination =
                dirname(APPROOT)
                . '/'
                . $path;

            $this->ensureDirectory(
                dirname($destination)
            );

            if (!copy($source, $destination)) {
                throw new RuntimeException(
                    'Could not install ' . $path . '.'
                );
            }
        }
    }

    /**
     * Verify every installed release-controlled file.
     *
     * @param array $manifest Files manifest.
     * @return void
     */
    private function verifyInstalledFiles(
        array $manifest
    ): void {
        foreach (
            $this->getManifestFiles($manifest)
            as $path => $hash
        ) {
            $file =
                dirname(APPROOT)
                . '/'
                . $path;

            if (!is_file($file)) {
                throw new RuntimeException(
                    'Installed Core is missing ' . $path . '.'
                );
            }

            $this->verifyFileHash(
                $file,
                (string) $hash
            );
        }
    }

    /**
     * Restore a previous Core backup.
     *
     * @param string $backupDir Backup directory.
     * @return void
     */
    private function restoreBackup(
        string $backupDir
    ): void {
        $stateFile =
            $backupDir
            . '/backup.json';

        if (!is_file($stateFile)) {
            throw new RuntimeException(
                'Rollback state is missing.'
            );
        }

        $raw = file_get_contents(
            $stateFile
        );

        $state = $raw !== false
            ? json_decode($raw, true)
            : null;

        if (!is_array($state)) {
            throw new RuntimeException(
                'Rollback state is invalid.'
            );
        }

        foreach ($state as $path => $existed) {
            /*
             * Rollback state is produced only from validated release
             * paths, but enforce the ownership boundary here as well.
             */
            if (
                $this->isInstallationOwnedPath(
                    (string) $path
                )
            ) {
                throw new RuntimeException(
                    'Rollback state contains installation-owned content.'
                );
            }

            $destination =
                dirname(APPROOT)
                . '/'
                . $path;

            if ($existed) {
                $source =
                    $backupDir
                    . '/files/'
                    . $path;

                if (!is_file($source)) {
                    throw new RuntimeException(
                        'Rollback file is missing: '
                        . $path
                    );
                }

                $this->ensureDirectory(
                    dirname($destination)
                );

                if (!copy($source, $destination)) {
                    throw new RuntimeException(
                        'Could not restore ' . $path . '.'
                    );
                }

                continue;
            }

            if (is_file($destination)) {
                unlink($destination);
            }
        }

        $this->removeDirectory(
            $backupDir
        );
    }

    /**
     * Run a declared SQL migration.
     *
     * @param string $stageDir Stage directory.
     * @param string $migration Relative migration path.
     * @return void
     */
    private function runMigration(
        string $stageDir,
        string $migration
    ): void {
        $this->validateRelativePath(
            $migration
        );

        $file =
            $stageDir
            . '/'
            . $migration;

        if (!is_file($file)) {
            throw new RuntimeException(
                'Declared database migration is missing.'
            );
        }

        $sql = file_get_contents(
            $file
        );

        if (
            $sql === false
            || trim($sql) === ''
        ) {
            throw new RuntimeException(
                'Database migration is empty.'
            );
        }

        $mysqli = new mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );

        if ($mysqli->connect_errno) {
            throw new RuntimeException(
                'Database connection failed during migration.'
            );
        }

        $mysqli->set_charset(
            'utf8mb4'
        );

        if (!$mysqli->multi_query($sql)) {
            $error = $mysqli->error;

            $mysqli->close();

            throw new RuntimeException(
                'Database migration failed: '
                . $error
            );
        }

        do {
            if (
                $result =
                    $mysqli->store_result()
            ) {
                $result->free();
            }

            if (!$mysqli->more_results()) {
                break;
            }

            if (!$mysqli->next_result()) {
                $error = $mysqli->error;

                $mysqli->close();

                throw new RuntimeException(
                    'Database migration failed: '
                    . $error
                );
            }
        } while (true);

        $mysqli->close();
    }

    /**
     * Download a remote file.
     *
     * @param string $url HTTPS URL.
     * @param string $destination Local destination.
     * @return void
     */
    private function downloadFile(
        string $url,
        string $destination
    ): void {
        $this->requireHttpsUrl(
            $url
        );

        $context = stream_context_create([
            'http' => [
                'timeout' => 60,
                'follow_location' => 1
            ]
        ]);

        $data = @file_get_contents(
            $url,
            false,
            $context
        );

        if (
            $data === false
            || $data === ''
        ) {
            throw new RuntimeException(
                'Update package download failed.'
            );
        }

        if (
            file_put_contents(
                $destination,
                $data,
                LOCK_EX
            ) === false
        ) {
            throw new RuntimeException(
                'Downloaded update package could not be written.'
            );
        }
    }

    /**
     * Fetch and decode JSON over HTTPS.
     *
     * @param string $url HTTPS URL.
     * @return array
     */
    private function fetchJson(
        string $url
    ): array {
        $this->requireHttpsUrl(
            $url
        );

        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'follow_location' => 1,
                'header' =>
                    "Accept: application/json\r\n"
            ]
        ]);

        $raw = @file_get_contents(
            $url,
            false,
            $context
        );

        if (
            $raw === false
            || $raw === ''
        ) {
            throw new RuntimeException(
                'Could not retrieve update metadata.'
            );
        }

        $decoded = json_decode(
            $raw,
            true
        );

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Update metadata is invalid JSON.'
            );
        }

        return $decoded;
    }

    /**
     * Extract a release ZIP after checking archive paths.
     *
     * Installation-owned paths are rejected before extraction.
     *
     * @param string $archive ZIP archive.
     * @param string $destination Stage directory.
     * @return void
     */
    private function extractPackage(
        string $archive,
        string $destination
    ): void {
        $zip = new ZipArchive();

        if ($zip->open($archive) !== true) {
            throw new RuntimeException(
                'Update package could not be opened.'
            );
        }

        for (
            $index = 0;
            $index < $zip->numFiles;
            $index++
        ) {
            $name = $zip->getNameIndex(
                $index
            );

            if ($name === false) {
                $zip->close();

                throw new RuntimeException(
                    'Update package contains an invalid entry.'
                );
            }

            $this->validateRelativePath(
                $name
            );

            /*
             * Even an unlisted archive entry may not enter an
             * installation-owned path.
             */
            /* [AI:GPT-5.6 Sol | 2026-08-25 22:21:00 UTC] */
            if (
                $this->isInstallationOwnedPath(
                    $name
                )
            ) {
                $zip->close();

                throw new RuntimeException(
                    'Update package contains installation-owned content: '
                    . $name
                    . '.'
                );
            }
            /* [End AI:GPT-5.6 Sol] */
        }

        $this->ensureDirectory(
            $destination
        );

        if (!$zip->extractTo($destination)) {
            $zip->close();

            throw new RuntimeException(
                'Update package could not be staged.'
            );
        }

        $zip->close();
    }

    /**
     * Verify file SHA-256.
     *
     * @param string $file File.
     * @param string $expected Expected SHA-256.
     * @return void
     */
    private function verifyFileHash(
        string $file,
        string $expected
    ): void {
        $expected = strtolower(
            trim($expected)
        );

        if (
            !preg_match(
                '/^[a-f0-9]{64}$/',
                $expected
            )
        ) {
            throw new RuntimeException(
                'Expected SHA-256 value is invalid.'
            );
        }

        $actual = hash_file(
            'sha256',
            $file
        );

        if (
            $actual === false
            || !hash_equals(
                $expected,
                strtolower($actual)
            )
        ) {
            throw new RuntimeException(
                'SHA-256 verification failed for '
                . basename($file)
                . '.'
            );
        }
    }

    /**
     * Validate an archive/manifest relative path.
     *
     * @param string $path Path.
     * @return void
     */
    private function validateRelativePath(
        string $path
    ): void {
        $normalized = str_replace(
            '\\',
            '/',
            $path
        );

        if (
            $normalized === ''
            || str_contains(
                $normalized,
                "\0"
            )
            || str_starts_with(
                $normalized,
                '/'
            )
            || preg_match(
                '/^[a-zA-Z]:\//',
                $normalized
            )
        ) {
            throw new RuntimeException(
                'Unsafe update path detected.'
            );
        }

        foreach (
            explode(
                '/',
                trim($normalized, '/')
            )
            as $segment
        ) {
            if (
                $segment === '..'
                || $segment === '.'
            ) {
                throw new RuntimeException(
                    'Unsafe update path detected.'
                );
            }
        }
    }

    /**
     * Require an HTTPS URL.
     *
     * @param string $url URL.
     * @return void
     */
    private function requireHttpsUrl(
        string $url
    ): void {
        if (
            !filter_var(
                $url,
                FILTER_VALIDATE_URL
            )
        ) {
            throw new RuntimeException(
                'Update URL is invalid.'
            );
        }

        if (
            strtolower(
                (string) parse_url(
                    $url,
                    PHP_URL_SCHEME
                )
            ) !== 'https'
        ) {
            throw new RuntimeException(
                'Update URL must use HTTPS.'
            );
        }
    }

    /**
     * Enter maintenance mode.
     *
     * @param string $targetVersion Target version.
     * @return void
     */
    private function enterMaintenance(
        string $targetVersion
    ): void {
        $this->ensureDirectory(
            $this->runtimeDir
        );

        $state = json_encode(
            [
                'maintenance' => true,
                'target_version' => $targetVersion,
                'started_at' => gmdate('c')
            ],
            JSON_PRETTY_PRINT
        );

        if (
            $state === false
            || file_put_contents(
                $this->maintenanceFile,
                $state . PHP_EOL,
                LOCK_EX
            ) === false
        ) {
            throw new RuntimeException(
                'Could not enter maintenance mode.'
            );
        }
    }

    /**
     * Leave maintenance mode.
     *
     * @return void
     */
    private function leaveMaintenance(): void
    {
        if (is_file($this->maintenanceFile)) {
            unlink(
                $this->maintenanceFile
            );
        }
    }

    /**
     * Write updater status.
     *
     * @param string $status Status.
     * @param string $stage Stage.
     * @param int $percent Percentage.
     * @param string $current Current version.
     * @param string|null $target Target version.
     * @param string $message Status message.
     * @param string|null $error Error.
     * @return void
     */
    private function setStatus(
        string $status,
        string $stage,
        int $percent,
        string $current,
        ?string $target,
        string $message,
        ?string $error = null
    ): void {
        $this->ensureDirectory(
            $this->runtimeDir
        );

        $payload = [
            'status' => $status,
            'stage' => $stage,
            'percent' => $percent,
            'current_version' => $current,
            'target_version' => $target,
            'message' => $message,
            'error' => $error,
            'updated_at' => gmdate('c')
        ];

        $encoded = json_encode(
            $payload,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
        );

        if ($encoded === false) {
            return;
        }

        file_put_contents(
            $this->statusFile,
            $encoded . PHP_EOL,
            LOCK_EX
        );
    }

    /**
     * Create directory when required.
     *
     * @param string $directory Directory.
     * @return void
     */
    private function ensureDirectory(
        string $directory
    ): void {
        if (
            !is_dir($directory)
            && !mkdir(
                $directory,
                0755,
                true
            )
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Could not create updater directory.'
            );
        }
    }

    /**
     * Remove directory recursively.
     *
     * @param string $directory Directory.
     * @return void
     */
    private function removeDirectory(
        string $directory
    ): void {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir(
            $directory
        );

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if (
                $item === '.'
                || $item === '..'
            ) {
                continue;
            }

            $path =
                $directory
                . '/'
                . $item;

            if (
                is_link($path)
                || is_file($path)
            ) {
                unlink($path);
                continue;
            }

            if (is_dir($path)) {
                $this->removeDirectory(
                    $path
                );
            }
        }

        rmdir(
            $directory
        );
    }
}
/* [End AI:GPT-5.6 Sol] */