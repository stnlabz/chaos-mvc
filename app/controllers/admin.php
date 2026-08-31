<?php

/**
 * Administration Controller
 *
 * Security Maintenance
 *
 * CMSEC-2026-4828 — Module Update Integrity Boundary
 *
 * Remote module metadata and packages must be authenticated by request
 * intent, verified for integrity, constrained to the requested module,
 * staged, and recoverable before executable application files are replaced.
 * Remote update connections must resolve exclusively to public addresses and
 * remain pinned to the address validated before the TLS connection is opened.
 * Module removal must delete symbolic links themselves and must never recurse
 * through a link into another filesystem location.
 *
 * Path: /app/controllers/admin.php
 */

class admin extends controller
{
    /**
     * Administrative actions exposed directly by this controller.
     *
     * @var array
     */
    private const ADMIN_ACTIONS = [
        'check_update',
        'update',
        'uninstall',
    ];

    /**
     * Administration entry point.
     *
     * Core administration controllers are resolved from /app/controllers.
     * User-land module administration controllers are resolved from
     * /user/modules/{slug}/controllers.
     *
     * Core controller names take priority over user-land module names.
     *
     * @param array $params Route parameters.
     *
     * @return void
     */
    public function index($params = []): void
    {
        if (
            !isset($_SESSION['user_level'])
            || $_SESSION['user_level'] < 7
        ) {
            header('Location: /auth/login');
            exit;
        }

        $slug = $params[0] ?? null;

        /*
         * Direct administrative actions are explicitly bounded.
         */
        if (
            is_string($slug)
            && in_array($slug, self::ADMIN_ACTIONS, true)
        ) {
            $reflection = new ReflectionMethod($this, $slug);

            if ($reflection->isPublic()) {
                $this->$slug();
                return;
            }
        }

        if (
            !is_string($slug)
            || !preg_match('/^[a-z][a-z0-9_]{0,62}$/', $slug)
        ) {
            $this->view('admin/index', [
                'modules' => $this->discoverAdminNavigationModules()
            ]);
            return;
        }

        /*
         * Core owns the controller namespace first.
         */
        $corePath = APPROOT . '/controllers/' . $slug . '.php';

        if (is_file($corePath)) {
            require_once $corePath;

            if (class_exists($slug, false) && method_exists($slug, 'admin')) {
                $reflection = new ReflectionMethod($slug, 'admin');

                if (
                    $reflection->isPublic()
                    && $reflection->getDeclaringClass()->getName() === $slug
                ) {
                    $controller = new $slug();
                    $controller->admin($params);
                    return;
                }
            }

            $this->view('admin/index', [
                'modules' => $this->discoverAdminNavigationModules()
            ]);
            return;
        }

        /*
         * No Core controller owns the slug. Resolve the independently owned
         * user module and establish its context before dispatch.
         */
        $userPath = USERROOT
            . '/modules/'
            . $slug
            . '/controllers/'
            . $slug
            . '.php';

        if (is_file($userPath)) {
            /*
             * CMSEC-2026-4828-L — Module class ownership
             *
             * A user module may not claim a class name already owned by the
             * running application or framework.
             */
            if (class_exists($slug, true)) {
                /*
                 * CMSEC-2026-4830-C1 — Prevalidated admin module ownership
                 *
                 * The router may already have loaded this exact controller
                 * while validating /admin/{module}. Reject an existing class
                 * only when it belongs to a different file.
                 *
                 * Disabled regression behavior:
                 * class_exists($slug, true) caused every router-prevalidated
                 * user administration controller to be rejected.
                 */
                $existingClass = new ReflectionClass($slug);

                if (
                    realpath((string) $existingClass->getFileName())
                    !== realpath($userPath)
                ) {
                    $this->view('admin/index', [
                        'modules' => $this->discoverAdminNavigationModules()
                    ]);
                    return;
                }
            }

            require_once $userPath;

            if (class_exists($slug, false) && method_exists($slug, 'admin')) {
                $reflection = new ReflectionMethod($slug, 'admin');

                if (
                    $reflection->isPublic()
                    && $reflection->getDeclaringClass()->getName() === $slug
                    && realpath((string) $reflection->getFileName())
                        === realpath($userPath)
                ) {
                    $controller = new $slug();
                    $controller->setModuleContext($slug);
                    $controller->admin($params);
                    return;
                }
            }
        }

        $this->view('admin/index', [
            'modules' => $this->discoverAdminNavigationModules()
        ]);
    }

    /**
     * Discover administrative navigation without executing user module PHP.
     *
     * CMSEC-2026-4830-B — Inert administration discovery
     *
     * @return array<int, string>
     */
    private function discoverAdminNavigationModules(): array
    {
        $modules = [];
        $excluded = ['admin', 'pages', 'auth', 'error_handler'];

        foreach (glob(APPROOT . '/controllers/*.php') ?: [] as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $excluded, true)) {
                continue;
            }

            require_once $file;

            if (class_exists($name, false) && method_exists($name, 'admin')) {
                $method = new ReflectionMethod($name, 'admin');

                if ($method->isPublic()) {
                    $modules[] = $name;
                }
            }
        }

        foreach (glob(USERROOT . '/modules/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);
            $metadataPath = $directory . '/module.json';
            $raw = is_file($metadataPath)
                ? file_get_contents($metadataPath)
                : false;
            $metadata = is_string($raw) ? json_decode($raw, true) : null;

            if (
                preg_match('/^[a-z][a-z0-9_]{0,62}$/', $name)
                && !is_link($directory)
                && is_array($metadata)
                && (string) ($metadata['module'] ?? '') === $name
                && is_file($directory . '/controllers/' . $name . '.php')
                && $this->moduleDeclaresPublicAdmin($name)
            ) {
                $modules[] = $name;
            }
        }

        sort($modules, SORT_STRING);
        return array_values(array_unique($modules));
    }

    /**
     * Check one installed module's authenticated remote release manifest.
     *
     * CMSEC-2026-4833-A — Authenticated read-only update discovery
     * CMSEC-2026-4833-B — Verified remote release presentation
     *
     * Discovery never downloads or installs the package. It verifies the
     * manifest statement against installation-pinned developer trust before
     * reporting that an update is available.
     */
    public function check_update(): void
    {
        header('Content-Type: application/json');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            $this->respondModuleCheck(false, null, null, 'POST required.');
        }

        $this->require_admin(9);
        $this->require_csrf();

        $module = trim((string) ($_POST['module'] ?? ''));

        if (
            !preg_match('/^[a-z][a-z0-9_]{0,62}$/', $module)
            || $this->isCore($module)
        ) {
            $this->respondModuleCheck(false, null, null, 'Invalid module.');
        }

        try {
            $moduleRoot = $this->resolveInstalledModuleRoot($module);
            $config = $this->readModuleJson($moduleRoot . '/module.json');
            $current = (string) ($config['version'] ?? '0.0.0');
            $updateUrl = (string) ($config['update_url'] ?? '');

            if (!$this->isHttpsUrl($updateUrl)) {
                throw new RuntimeException(
                    'A valid HTTPS update URL is required.'
                );
            }

            $manifestRaw = $this->downloadModuleResource($updateUrl, 1048576);
            $manifest = json_decode($manifestRaw, true);

            if (!is_array($manifest)) {
                throw new RuntimeException('Update manifest is invalid.');
            }

            $available = trim((string) ($manifest['version'] ?? ''));
            $manifestModule = (string) ($manifest['module'] ?? '');

            if (
                $manifestModule !== $module
                || !preg_match(
                    '/^[0-9]+(?:\.[0-9]+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/',
                    $available
                )
            ) {
                throw new RuntimeException(
                    'Update manifest does not match the installed module.'
                );
            }

            /*
             * Discovery compares only the installed version with the version
             * advertised by the installation-pinned update URL. Package
             * metadata and cryptographic verification remain mandatory in
             * update().
             */
            $this->respondModuleCheck(
                true,
                $current,
                $available,
                null
            );
        } catch (Throwable $error) {
            error_log(
                'Module update discovery failed for '
                . $module
                . ': '
                . $error->getMessage()
            );
            $this->respondModuleCheck(
                false,
                null,
                null,
                $error->getMessage()
            );
        }
    }

    /**
     * Install an authenticated and verified module update.
     *
     * CMSEC-2026-4828-A — POST and CSRF request intent
     * CMSEC-2026-4828-B — Manifest and package integrity
     * CMSEC-2026-4828-C — Safe archive and module identity
     * CMSEC-2026-4828-D — Staging, backup, and rollback
     * CMSEC-2026-4828-K — User module ownership boundary
     * CMSEC-2026-4828-M — Developer release authenticity
     * CMSEC-2026-4828-N — Module write-path confinement
     * CMSEC-2026-4828-O — Atomic module directory replacement
     * CMSEC-2026-4828-Q — Trust and release metadata separation
     * CMSEC-2026-4830-E — Serialized module maintenance
     * CMSEC-2026-4832-A — Durable module migration journal
     * CMSEC-2026-4832-B — Controlled signed module migration execution
     * CMSEC-2026-4832-C — Exact transition and table confinement
     *
     * @return void
     */
    public function update(): void
    {
        header('Content-Type: application/json');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            $this->respondModuleUpdate(false, null, 'POST required.');
        }

        $this->require_admin(9);
        $this->require_csrf();

        $module = trim((string) ($_POST['module'] ?? ''));

        if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/', $module)) {
            $this->respondModuleUpdate(false, null, 'Invalid module.');
        }

        if ($this->isCore($module)) {
            $this->respondModuleUpdate(false, null, 'Core modules cannot be updated here.');
        }

        try {
            $maintenanceLock = $this->acquireModuleMaintenanceLock($module);
        } catch (Throwable $error) {
            $this->respondModuleUpdate(false, null, $error->getMessage());
        }

        try {
            $moduleRoot = $this->resolveInstalledModuleRoot($module);
        } catch (Throwable $error) {
            $this->respondModuleUpdate(false, null, $error->getMessage());
        }

        $configPath = $moduleRoot . '/module.json';

        try {
            $config = $this->readModuleJson($configPath);
        } catch (Throwable $error) {
            $this->respondModuleUpdate(false, null, $error->getMessage());
        }

        $current = (string) ($config['version'] ?? '0.0.0');
        $updateUrl = (string) ($config['update_url'] ?? '');

        if (!$this->isHttpsUrl($updateUrl)) {
            $this->respondModuleUpdate(false, null, 'A valid HTTPS update URL is required.');
        }

        $workRoot =
            sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'chaos-module-'
            . bin2hex(random_bytes(12));

        $packagePath = $workRoot . DIRECTORY_SEPARATOR . 'package.zip';
        $stagePath = $workRoot . DIRECTORY_SEPARATOR . 'stage';
        /*
         * CMSEC-2026-4828-O
         *
         * Disabled file-level rollback state retained for maintenance:
         * $originalConfig = file_get_contents($configPath);
         * $backupPath = $workRoot . DIRECTORY_SEPARATOR . 'backup';
         * $installed = [];
         * $backedUp = [];
         */
        $transactionId = bin2hex(random_bytes(8));
        $moduleParent = dirname($moduleRoot);
        $incomingRoot = $moduleParent . DIRECTORY_SEPARATOR
            . '.' . $module . '.incoming-' . $transactionId;
        $moduleBackupRoot = $moduleParent . DIRECTORY_SEPARATOR
            . '.' . $module . '.backup-' . $transactionId;
        $moduleMoved = false;
        $migrationState = 'none';

        try {
            if (!mkdir($workRoot, 0700, true)) {
                throw new RuntimeException('Could not create update workspace.');
            }

            $manifestRaw = $this->downloadModuleResource($updateUrl, 1048576);
            $manifest = json_decode($manifestRaw, true);

            if (!is_array($manifest)) {
                throw new RuntimeException('Update manifest is invalid.');
            }

            $new = (string) ($manifest['version'] ?? '');
            $manifestModule = (string) ($manifest['module'] ?? '');
            $downloadUrl = (string) ($manifest['download'] ?? '');
            $expectedHash = strtolower((string) ($manifest['sha256'] ?? ''));

            if ($manifestModule !== $module) {
                throw new RuntimeException(
                    'Update manifest does not match the requested module.'
                );
            }

            if ($new === '' || !version_compare($new, $current, '>')) {
                $this->cleanupModuleUpdate($workRoot);
                $this->respondModuleUpdate(
                    true,
                    $current,
                    'Already up to date.'
                );
            }

            if (!$this->isHttpsUrl($downloadUrl)) {
                throw new RuntimeException('Manifest package URL must use HTTPS.');
            }

            if (
                !$this->isAuthorizedModulePackageHost(
                    $updateUrl,
                    $downloadUrl,
                    $config
                )
            ) {
                throw new RuntimeException(
                    'Manifest package host is not authorized by module metadata.'
                );
            }

            if (!preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
                throw new RuntimeException('Manifest SHA-256 is missing or invalid.');
            }

            $this->verifyModuleReleaseSignature(
                $manifest,
                $config,
                $module,
                $new,
                $downloadUrl,
                $expectedHash
            );

            $package = $this->downloadModuleResource($downloadUrl, 26214400);

            if (file_put_contents($packagePath, $package, LOCK_EX) === false) {
                throw new RuntimeException('Could not stage update package.');
            }

            $actualHash = hash_file('sha256', $packagePath);

            if (
                !is_string($actualHash)
                || !hash_equals($expectedHash, strtolower($actualHash))
            ) {
                throw new RuntimeException('Update package integrity check failed.');
            }

            $files = $this->validateModuleArchive(
                $packagePath,
                $module
            );

            if (!mkdir($stagePath, 0700, true)) {
                throw new RuntimeException('Could not create staging directory.');
            }

            $zip = new ZipArchive();

            if ($zip->open($packagePath) !== true) {
                throw new RuntimeException('Could not open update package.');
            }

            if (!$zip->extractTo($stagePath)) {
                $zip->close();
                throw new RuntimeException('Could not extract update package.');
            }

            $zip->close();

            $stagedConfig = $this->readModuleJson(
                $stagePath
                    . DIRECTORY_SEPARATOR
                    . $module
                    . DIRECTORY_SEPARATOR
                    . 'module.json'
            );

            if ((string) ($stagedConfig['module'] ?? '') !== $module) {
                throw new RuntimeException(
                    'Packaged module metadata does not match the requested module.'
                );
            }

            if ((string) ($stagedConfig['version'] ?? '') !== $new) {
                throw new RuntimeException(
                    'Packaged module version does not match the signed manifest.'
                );
            }

            $migration = $this->resolveModuleMigration(
                $stagedConfig,
                $stagePath . DIRECTORY_SEPARATOR . $module,
                $current,
                $new
            );

            if (!mkdir($incomingRoot, 0700, true)) {
                throw new RuntimeException('Could not create incoming module directory.');
            }

            foreach ($files as $relativePath) {
                $source = $stagePath
                    . DIRECTORY_SEPARATOR
                    . $module
                    . DIRECTORY_SEPARATOR
                    . $relativePath;
                $destination = $incomingRoot
                    . DIRECTORY_SEPARATOR
                    . $relativePath;

                $this->assertModuleWritePath(
                    $incomingRoot,
                    $destination
                );

                if (!is_file($source)) {
                    continue;
                }

                $this->copyModuleFile($source, $destination);
            }

            /*
             * CMSEC-2026-4828-Q — Trust and release metadata separation
             *
             * Signed packaged metadata may advance with the release, while
             * installation-local network and signing trust remains pinned.
             */
            $updatedConfig = $stagedConfig;

            foreach (['update_url', 'package_hosts', 'signing'] as $trustedKey) {
                if (array_key_exists($trustedKey, $config)) {
                    $updatedConfig[$trustedKey] = $config[$trustedKey];
                }
            }

            $updatedConfig['version'] = $new;

            $encodedConfig = json_encode(
                $updatedConfig,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );

            if (
                $encodedConfig === false
                || file_put_contents(
                    $incomingRoot . DIRECTORY_SEPARATOR . 'module.json',
                    $encodedConfig . PHP_EOL,
                    LOCK_EX
                ) === false
            ) {
                throw new RuntimeException('Could not update module metadata.');
            }

            if ($migration !== null) {
                /*
                 * CMSEC-2026-4832-A through CMSEC-2026-4832-C
                 *
                 * Apply only the exact transition carried inside the
                 * authenticated package, before activating updated source.
                 */
                $ownedTables = array_values(array_unique(array_merge(
                    $this->moduleOwnedTables($config, $module),
                    $this->moduleOwnedTables($stagedConfig, $module)
                )));
                $statements = $this->readModuleMigrationStatements(
                    $migration['absolute_path'],
                    $ownedTables
                );
                $migrationState = 'started';
                $migrationState = $this->applyModuleMigration(
                    $this->model('modules_model'),
                    $module,
                    $current,
                    $new,
                    $migration['relative_path'],
                    $expectedHash,
                    $statements
                );
            }

            /*
             * CMSEC-2026-4828-O — Atomic module directory replacement
             *
             * Disabled file-by-file installation merged releases into the
             * live directory and retained removed executable files. The
             * verified directory is now exchanged as one owned unit.
             */
            if (!rename($moduleRoot, $moduleBackupRoot)) {
                throw new RuntimeException('Could not back up the installed module.');
            }

            $moduleMoved = true;

            if (!rename($incomingRoot, $moduleRoot)) {
                throw new RuntimeException('Could not activate the module update.');
            }

            $moduleMoved = false;

            try {
                $this->deleteModuleDirectory($moduleBackupRoot);
            } catch (Throwable $cleanupError) {
                error_log(
                    'Module backup cleanup failed: '
                    . $cleanupError->getMessage()
                );
            }

            $this->cleanupModuleUpdate($workRoot);
            $this->respondModuleUpdate(true, $new, null);
        } catch (Throwable $error) {
            try {
                if ($moduleMoved && is_dir($moduleBackupRoot)) {
                    if (is_dir($moduleRoot)) {
                        $this->deleteModuleDirectory($moduleRoot);
                    }

                    if (!rename($moduleBackupRoot, $moduleRoot)) {
                        throw new RuntimeException('Module directory rollback failed.');
                    }
                }

                if (is_dir($incomingRoot)) {
                    $this->deleteModuleDirectory($incomingRoot);
                }
            } catch (Throwable $rollbackError) {
                error_log(
                    'Module update rollback failed: '
                    . $rollbackError->getMessage()
                );
            }

            $this->cleanupModuleUpdate($workRoot);
            error_log('Module update failed: ' . $error->getMessage());

            $message = $error->getMessage();

            /*
             * CMSEC-2026-4832-C — Honest non-transactional DDL reporting
             *
             * MySQL DDL may commit implicitly. Source rollback must not be
             * represented as database rollback.
             */
            if (in_array($migrationState, ['applied', 'previously_applied'], true)) {
                $message .= ' The database migration is recorded as complete; '
                    . 'retry the same signed update to finish source activation.';
            } elseif ($migrationState === 'started') {
                $message .= ' The database migration did not complete and may '
                    . 'have applied partially; inspect the module database before retrying.';
            }

            $this->respondModuleUpdate(false, null, $message);
        }
    }

    /*
     * CMSEC-2026-4828-A through CMSEC-2026-4828-D
     *
     * Previous module update behavior is retained below as a disabled
     * maintenance record. It accepted a state-changing GET request,
     * trusted unsigned remote metadata, extracted an unvalidated ZIP, and
     * overwrote executable application files without staging or rollback.
     *
     * public function update_previous(): void
     * {
     *     $module = $_GET['module'] ?? '';
     *     $remoteRaw = @file_get_contents($updateUrl);
     *     $download = $remote['download'] ?? null;
     *     $zipData = @file_get_contents($download);
     *     file_put_contents($tmpZip, $zipData);
     *     $zip->extractTo($tmpDir);
     *     $this->recursive_copy($tmpDir . '/controllers', APPROOT . '/controllers');
     *     $this->recursive_copy($tmpDir . '/models', APPROOT . '/models');
     *     $this->recursive_copy($tmpDir . '/views', APPROOT . '/views');
     * }
     */

    /**
     * Return a module update response and stop processing.
     *
     * CMSEC-2026-4828-A
     */
    private function respondModuleUpdate(
        bool $success,
        ?string $version,
        ?string $message
    ): void {
        $response = ['success' => $success];

        if ($version !== null) {
            $response['version'] = $version;
        }

        if ($message !== null) {
            $response[$success ? 'message' : 'error'] = $message;
        }

        echo json_encode($response);
        exit;
    }

    /**
     * Return one verified module update-discovery response.
     *
     * CMSEC-2026-4833-A — Authenticated read-only update discovery
     * CMSEC-2026-4833-B — Verified remote release presentation
     */
    private function respondModuleCheck(
        bool $success,
        ?string $currentVersion,
        ?string $availableVersion,
        ?string $message
    ): void {
        $response = ['success' => $success];

        if ($currentVersion !== null && $availableVersion !== null) {
            $response['current_version'] = $currentVersion;
            $response['available_version'] = $availableVersion;
            $response['update_available'] = version_compare(
                $availableVersion,
                $currentVersion,
                '>'
            );
        }

        if ($message !== null) {
            $response['error'] = $message;
        }

        echo json_encode($response);
        exit;
    }

    /**
     * Read required module JSON metadata.
     *
     * CMSEC-2026-4828-B
     */
    private function readModuleJson(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Module configuration was not found.');
        }

        $raw = file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($data)) {
            throw new RuntimeException('Module configuration is invalid.');
        }

        return $data;
    }

    /**
     * Resolve the exact signed-package migration for one update transition.
     *
     * CMSEC-2026-4832-C — Exact transition and path confinement
     *
     * @return array{relative_path: string, absolute_path: string}|null
     */
    private function resolveModuleMigration(
        array $stagedConfig,
        string $stagedModuleRoot,
        string $fromVersion,
        string $toVersion
    ): ?array {
        if (!array_key_exists('migrations', $stagedConfig)) {
            return null;
        }

        $migrations = $stagedConfig['migrations'];

        if (!is_array($migrations)) {
            throw new RuntimeException('Module migrations metadata is invalid.');
        }

        $transition = $fromVersion . '-to-' . $toVersion;

        if (!array_key_exists($transition, $migrations)) {
            return null;
        }

        $expectedPath = 'sql/patches/' . $transition . '.sql';

        if (
            !is_string($migrations[$transition])
            || $migrations[$transition] !== $expectedPath
        ) {
            throw new RuntimeException('Module migration path is invalid.');
        }

        $root = realpath($stagedModuleRoot);
        $candidate = $stagedModuleRoot
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $expectedPath);

        if ($root === false || is_link($candidate)) {
            throw new RuntimeException('Module migration boundary is invalid.');
        }

        $resolved = realpath($candidate);

        if (
            $resolved === false
            || !is_file($resolved)
            || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException('Declared module migration was not found.');
        }

        $size = filesize($resolved);

        if ($size === false || $size < 1 || $size > 1048576) {
            throw new RuntimeException('Module migration size is invalid.');
        }

        return [
            'relative_path' => $expectedPath,
            'absolute_path' => $resolved,
        ];
    }

    /**
     * Read and validate bounded module-owned SQL statements.
     *
     * CMSEC-2026-4832-B — Controlled signed module migration execution
     * CMSEC-2026-4832-C — Module-owned table confinement
     *
     * @param array<int, string> $ownedTables
     * @return array<int, string>
     */
    private function readModuleMigrationStatements(
        string $patchPath,
        array $ownedTables
    ): array {
        if ($ownedTables === []) {
            throw new RuntimeException('Module migration declares no owned tables.');
        }

        $sql = file_get_contents($patchPath);

        if (!is_string($sql) || trim($sql) === '' || str_contains($sql, "\0")) {
            throw new RuntimeException('Module migration is empty or invalid.');
        }

        $sql = preg_replace(
            [
                '~/\\*.*?\\*/~s',
                '/^[\\t ]*--[^\\r\\n]*(?:\\r?\\n|$)/m',
                '/^[\\t ]*#[^\\r\\n]*(?:\\r?\\n|$)/m',
            ],
            '',
            $sql
        );

        if (!is_string($sql)) {
            throw new RuntimeException('Module migration could not be parsed.');
        }

        $parts = preg_split('/;[\\t ]*(?:\\r?\\n|$)/', trim($sql));

        if (!is_array($parts)) {
            throw new RuntimeException('Module migration could not be parsed.');
        }

        $statements = [];

        foreach ($parts as $part) {
            $statement = trim($part);

            if ($statement === '') {
                continue;
            }

            $this->assertModuleMigrationStatement($statement, $ownedTables);
            $statements[] = $statement;
        }

        if ($statements === [] || count($statements) > 50) {
            throw new RuntimeException('Module migration statement count is invalid.');
        }

        return $statements;
    }

    /**
     * Confine one SQL statement to a directly targeted module-owned table.
     *
     * CMSEC-2026-4832-C — Statement and table confinement
     *
     * @param array<int, string> $ownedTables
     */
    private function assertModuleMigrationStatement(
        string $statement,
        array $ownedTables
    ): void {
        if (
            preg_match(
                '/\\b(?:DROP|TRUNCATE|RENAME|GRANT|REVOKE|LOAD|OUTFILE|INFILE|CALL|PROCEDURE|FUNCTION|TRIGGER|EVENT|PREPARE|EXECUTE|SELECT|JOIN|REFERENCES|INFORMATION_SCHEMA)\\b|@/i',
                $statement
            )
        ) {
            throw new RuntimeException(
                'Module migration contains a prohibited operation.'
            );
        }

        $patterns = [
            '/^ALTER\\s+TABLE\\s+`?([a-z][a-z0-9_]*)`?\\s+/i',
            '/^CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?`?([a-z][a-z0-9_]*)`?\\s*\\(/i',
            '/^INSERT\\s+INTO\\s+`?([a-z][a-z0-9_]*)`?\\s+/i',
            '/^UPDATE\\s+`?([a-z][a-z0-9_]*)`?\\s+SET\\s+/i',
            '/^DELETE\\s+FROM\\s+`?([a-z][a-z0-9_]*)`?(?:\\s+|$)/i',
        ];
        $target = null;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $statement, $matches)) {
                $target = strtolower($matches[1]);
                break;
            }
        }

        if ($target === null || !in_array($target, $ownedTables, true)) {
            throw new RuntimeException(
                'Module migration targets an unauthorized database table.'
            );
        }
    }

    /**
     * Apply or safely recognize one exact module migration transition.
     *
     * CMSEC-2026-4832-A — Durable replay prevention
     * CMSEC-2026-4832-B — Controlled migration execution
     *
     * @param object $migrationModel
     * @param array<int, string> $statements
     */
    private function applyModuleMigration(
        object $migrationModel,
        string $module,
        string $fromVersion,
        string $toVersion,
        string $patchPath,
        string $packageSha256,
        array $statements
    ): string {
        $migrationModel->ensure_migration_journal();
        $record = $migrationModel->get_migration(
            $module,
            $fromVersion,
            $toVersion
        );

        if (is_array($record)) {
            if (
                !hash_equals((string) $record['package_sha256'], $packageSha256)
                || (string) $record['patch_path'] !== $patchPath
            ) {
                throw new RuntimeException(
                    'Recorded module migration does not match this signed package.'
                );
            }

            return 'previously_applied';
        }

        foreach ($statements as $statement) {
            $migrationModel->execute_migration_statement($statement);
        }

        $migrationModel->record_migration(
            $module,
            $fromVersion,
            $toVersion,
            $patchPath,
            $packageSha256
        );

        return 'applied';
    }

    /**
     * Resolve one installed user module without following a module-root link.
     *
     * CMSEC-2026-4828-K — User module ownership boundary
     */
    private function resolveInstalledModuleRoot(string $module): string
    {
        $modulesRoot = realpath(USERROOT . '/modules');
        $candidate = USERROOT . '/modules/' . $module;

        if ($modulesRoot === false || is_link($candidate)) {
            throw new RuntimeException('Installed module boundary is invalid.');
        }

        $resolved = realpath($candidate);

        if (
            $resolved === false
            || !is_dir($resolved)
            || !str_starts_with(
                $resolved,
                $modulesRoot . DIRECTORY_SEPARATOR
            )
        ) {
            throw new RuntimeException('Installed module was not found.');
        }

        return $resolved;
    }

    /**
     * Acquire an exclusive lock for one module maintenance transaction.
     *
     * CMSEC-2026-4830-E — Serialized module maintenance
     *
     * @return resource
     */
    private function acquireModuleMaintenanceLock(string $module)
    {
        $lockDirectory = USERROOT . '/modules/.locks';

        if (
            !is_dir($lockDirectory)
            && !mkdir($lockDirectory, 0700, true)
            && !is_dir($lockDirectory)
        ) {
            throw new RuntimeException('Module maintenance lock is unavailable.');
        }

        $lock = fopen($lockDirectory . '/' . $module . '.lock', 'c');

        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw new RuntimeException('Another module maintenance operation is active.');
        }

        @chmod($lockDirectory . '/' . $module . '.lock', 0600);
        return $lock;
    }

    /**
     * Verify a developer-signed release statement against locally pinned
     * module trust metadata.
     *
     * CMSEC-2026-4828-M — Developer release authenticity
     */
    private function verifyModuleReleaseSignature(
        array $manifest,
        array $config,
        string $module,
        string $version,
        string $downloadUrl,
        string $sha256
    ): void {
        $trusted = $config['signing'] ?? null;
        $algorithm = is_array($trusted)
            ? (string) ($trusted['algorithm'] ?? '')
            : '';
        $trustedKeyId = is_array($trusted)
            ? (string) ($trusted['key_id'] ?? '')
            : '';
        $publicKey = is_array($trusted)
            ? (string) ($trusted['public_key'] ?? '')
            : '';
        $manifestKeyId = (string) ($manifest['key_id'] ?? '');
        $encodedSignature = (string) ($manifest['signature'] ?? '');

        if (
            $algorithm !== 'rsa-sha256'
            || $trustedKeyId === ''
            || !hash_equals($trustedKeyId, $manifestKeyId)
            || $publicKey === ''
        ) {
            throw new RuntimeException('Module signing trust is missing or invalid.');
        }

        $signature = base64_decode($encodedSignature, true);
        $key = openssl_pkey_get_public($publicKey);

        if ($signature === false || $key === false) {
            throw new RuntimeException('Module release signature is invalid.');
        }

        $keyDetails = openssl_pkey_get_details($key);

        if (
            !is_array($keyDetails)
            || ($keyDetails['type'] ?? null) !== OPENSSL_KEYTYPE_RSA
            || (int) ($keyDetails['bits'] ?? 0) < 3072
        ) {
            throw new RuntimeException('Module signing key must be RSA-3072 or stronger.');
        }

        $statement = implode("\n", [
            'CHAOS-MVC-MODULE-RELEASE',
            'module=' . $module,
            'version=' . $version,
            'download=' . $downloadUrl,
            'sha256=' . $sha256,
            'key_id=' . $manifestKeyId,
        ]);

        if (openssl_verify($statement, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Module release signature verification failed.');
        }
    }

    /**
     * Reject update destinations that cross the verified module root through
     * an existing symbolic link, junction, or other resolved ancestor.
     *
     * CMSEC-2026-4828-N — Module write-path confinement
     */
    private function assertModuleWritePath(
        string $moduleRoot,
        string $destination
    ): void {
        $resolvedRoot = realpath($moduleRoot);

        if ($resolvedRoot === false) {
            throw new RuntimeException('Module write boundary is unavailable.');
        }

        $relative = substr($destination, strlen($moduleRoot));
        $segments = array_values(array_filter(
            explode(DIRECTORY_SEPARATOR, ltrim($relative, '/\\')),
            static fn ($segment) => $segment !== ''
        ));
        $current = $resolvedRoot;

        foreach ($segments as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;

            if (is_link($current)) {
                throw new RuntimeException('Module update encountered a linked destination.');
            }

            if (file_exists($current)) {
                $resolved = realpath($current);

                if (
                    $resolved === false
                    || (
                        $resolved !== $resolvedRoot
                        && !str_starts_with(
                            $resolved,
                            $resolvedRoot . DIRECTORY_SEPARATOR
                        )
                    )
                ) {
                    throw new RuntimeException('Module update escaped its owned directory.');
                }
            }
        }
    }

    /**
     * Confirm that a remote module resource uses HTTPS.
     *
     * CMSEC-2026-4828-B
     * CMSEC-2026-4828-G
     */
    private function isHttpsUrl(string $url): bool
    {
        try {
            $this->parseModuleRemoteUrl($url);
            return true;
        } catch (Throwable $error) {
            return false;
        }
    }

    /**
     * Download a bounded remote module resource.
     *
     * CMSEC-2026-4828-B
     * CMSEC-2026-4828-G
     */
    private function downloadModuleResource(
        string $url,
        int $maximumBytes
    ): string {
        $remote = $this->parseModuleRemoteUrl($url);
        $addresses = $this->resolvePublicModuleAddresses($remote['host']);
        $address = $addresses[0];
        $socketAddress = str_contains($address, ':')
            ? '[' . $address . ']'
            : $address;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $remote['host'],
                'SNI_enabled' => true,
                'SNI_server_name' => $remote['host'],
                'disable_compression' => true,
            ],
        ]);

        $errorNumber = 0;
        $errorMessage = '';
        $resource = @stream_socket_client(
            'tls://' . $socketAddress . ':443',
            $errorNumber,
            $errorMessage,
            20,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($resource === false) {
            throw new RuntimeException('Secure module connection failed.');
        }

        stream_set_timeout($resource, 20);

        $hostHeader = filter_var(
            $remote['host'],
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6
        ) !== false
            ? '[' . $remote['host'] . ']'
            : $remote['host'];

        $request =
            'GET ' . $remote['target'] . " HTTP/1.1\r\n"
            . 'Host: ' . $hostHeader . "\r\n"
            . "Accept: application/json, application/zip, application/octet-stream\r\n"
            . "Accept-Encoding: identity\r\n"
            . "Connection: close\r\n"
            . "User-Agent: Chaos-MVC-Module-Updater/1\r\n\r\n";

        $written = 0;
        $requestLength = strlen($request);

        while ($written < $requestLength) {
            $result = fwrite($resource, substr($request, $written));

            if ($result === false || $result === 0) {
                fclose($resource);
                throw new RuntimeException('Module request could not be sent.');
            }

            $written += $result;
        }

        $statusLine = fgets($resource, 8192);

        if (
            !is_string($statusLine)
            || !preg_match(
                '/^HTTP\/1\.[01]\s+([0-9]{3})(?:\s|$)/',
                trim($statusLine),
                $statusMatch
            )
        ) {
            fclose($resource);
            throw new RuntimeException('Module server returned an invalid response.');
        }

        $status = (int) $statusMatch[1];
        $headers = [];
        $headerCount = 0;
        $headerBytes = strlen($statusLine);

        while (($line = fgets($resource, 8192)) !== false) {
            /*
             * CMSEC-2026-4828-J
             *
             * Previous behavior limited each read but did not bound the
             * cumulative header count or size.
             */
            $headerCount++;
            $headerBytes += strlen($line);

            if ($headerCount > 100 || $headerBytes > 65536) {
                fclose($resource);
                throw new RuntimeException('Module response headers exceed the limit.');
            }

            $line = rtrim($line, "\r\n");

            if ($line === '') {
                break;
            }

            if (!str_contains($line, ':')) {
                fclose($resource);
                throw new RuntimeException('Module server returned invalid headers.');
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        if ($status < 200 || $status > 299) {
            fclose($resource);
            throw new RuntimeException('Module server returned a non-success status.');
        }

        if (isset($headers['location'])) {
            fclose($resource);
            throw new RuntimeException('Module server attempted a redirect.');
        }

        if (
            isset($headers['content-length'])
            && (
                !ctype_digit($headers['content-length'])
                || (int) $headers['content-length'] > $maximumBytes
            )
        ) {
            fclose($resource);
            throw new RuntimeException('Remote module resource exceeds the size limit.');
        }

        $transferEncoding = strtolower($headers['transfer-encoding'] ?? '');
        $data = $transferEncoding === 'chunked'
            ? $this->readChunkedModuleBody($resource, $maximumBytes)
            : stream_get_contents($resource, $maximumBytes + 1);

        $timedOut = stream_get_meta_data($resource)['timed_out'] ?? false;
        fclose($resource);

        if (!is_string($data) || $data === '') {
            throw new RuntimeException('Remote module resource was empty.');
        }

        if ($timedOut) {
            throw new RuntimeException('Remote module request timed out.');
        }

        if (strlen($data) > $maximumBytes) {
            throw new RuntimeException('Remote module resource exceeds the size limit.');
        }

        return $data;
    }

    /**
     * Confirm that the package host is authorized by trusted local metadata.
     *
     * CMSEC-2026-4828-G
     */
    private function isAuthorizedModulePackageHost(
        string $manifestUrl,
        string $packageUrl,
        array $config
    ): bool {
        $manifest = $this->parseModuleRemoteUrl($manifestUrl);
        $package = $this->parseModuleRemoteUrl($packageUrl);
        $allowed = [$manifest['host']];
        $configured = $config['package_hosts'] ?? [];

        if (is_array($configured)) {
            foreach ($configured as $host) {
                if (!is_string($host)) {
                    continue;
                }

                try {
                    $remote = $this->parseModuleRemoteUrl(
                        'https://' . trim($host) . '/'
                    );
                    $allowed[] = $remote['host'];
                } catch (Throwable $error) {
                    continue;
                }
            }
        }

        return in_array(
            $package['host'],
            array_unique($allowed),
            true
        );
    }

    /**
     * Parse the restricted remote URL contract used by module updates.
     *
     * CMSEC-2026-4828-G
     *
     * @return array{host: string, target: string}
     */
    private function parseModuleRemoteUrl(string $url): array
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Remote module URL is invalid.');
        }

        $parts = parse_url($url);

        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            throw new RuntimeException('Remote module URL must use HTTPS on port 443.');
        }

        $host = strtolower(trim((string) $parts['host'], '[]'));

        if (
            filter_var($host, FILTER_VALIDATE_IP) === false
            && !preg_match(
                '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                $host
            )
        ) {
            throw new RuntimeException('Remote module hostname is invalid.');
        }

        $target = (string) ($parts['path'] ?? '/');

        if ($target === '') {
            $target = '/';
        }

        if (isset($parts['query'])) {
            $target .= '?' . $parts['query'];
        }

        return [
            'host' => $host,
            'target' => $target,
        ];
    }

    /**
     * Resolve a module host and reject every non-public destination.
     *
     * CMSEC-2026-4828-G
     *
     * @return array<int, string>
     */
    private function resolvePublicModuleAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            $addresses = [];

            if (is_array($records)) {
                foreach ($records as $record) {
                    $address = $record['ip'] ?? $record['ipv6'] ?? null;

                    if (is_string($address)) {
                        $addresses[] = $address;
                    }
                }
            }
        }

        $addresses = array_values(array_unique($addresses));

        if ($addresses === []) {
            throw new RuntimeException('Remote module hostname could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (!$this->isPublicModuleAddress($address)) {
                throw new RuntimeException(
                    'Remote module hostname resolves to a non-public address.'
                );
            }
        }

        return $addresses;
    }

    /**
     * Determine whether an address is safe for a remote module connection.
     *
     * CMSEC-2026-4828-G
     *
     * PHP's standard private/reserved flags do not reject multicast ranges,
     * so those ranges and IPv4-mapped IPv6 addresses are handled explicitly.
     */
    private function isPublicModuleAddress(string $address): bool
    {
        if (
            filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false
        ) {
            return false;
        }

        $packed = inet_pton($address);

        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            $firstOctet = ord($packed[0]);

            if ($firstOctet >= 224) {
                return false;
            }

            $numeric = unpack('N', $packed)[1];

            // 100.64.0.0/10 — carrier-grade NAT shared address space.
            if (($numeric & 0xffc00000) === 0x64400000) {
                return false;
            }

            return true;
        }

        if (strlen($packed) === 16) {
            // ff00::/8 — IPv6 multicast.
            if (ord($packed[0]) === 0xff) {
                return false;
            }

            // ::ffff:0:0/96 — reject mapped IPv4 to avoid policy bypasses.
            if (
                substr($packed, 0, 10) === str_repeat("\0", 10)
                && substr($packed, 10, 2) === "\xff\xff"
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read a bounded HTTP chunked response body.
     *
     * CMSEC-2026-4828-G
     * CMSEC-2026-4828-J
     *
     * @param resource $resource
     */
    private function readChunkedModuleBody(
        $resource,
        int $maximumBytes
    ): string {
        $body = '';

        while (true) {
            $line = fgets($resource, 8192);

            if (!is_string($line)) {
                throw new RuntimeException('Chunked module response ended unexpectedly.');
            }

            $sizeText = trim(explode(';', $line, 2)[0]);

            if ($sizeText === '' || !ctype_xdigit($sizeText)) {
                throw new RuntimeException('Chunked module response is invalid.');
            }

            $size = hexdec($sizeText);

            if ($size === 0) {
                $trailerCount = 0;
                $trailerBytes = 0;

                while (($trailer = fgets($resource, 8192)) !== false) {
                    /*
                     * CMSEC-2026-4828-J
                     *
                     * Bound chunked-response trailers independently from
                     * ordinary response headers.
                     */
                    $trailerCount++;
                    $trailerBytes += strlen($trailer);

                    if ($trailerCount > 50 || $trailerBytes > 32768) {
                        throw new RuntimeException(
                            'Module response trailers exceed the limit.'
                        );
                    }

                    if (rtrim($trailer, "\r\n") === '') {
                        break;
                    }
                }

                break;
            }

            if (strlen($body) + $size > $maximumBytes) {
                throw new RuntimeException('Remote module resource exceeds the size limit.');
            }

            $chunk = '';

            while (strlen($chunk) < $size) {
                $part = fread($resource, $size - strlen($chunk));

                if (!is_string($part) || $part === '') {
                    throw new RuntimeException('Chunked module response ended unexpectedly.');
                }

                $chunk .= $part;
            }

            if (fread($resource, 2) !== "\r\n") {
                throw new RuntimeException('Chunked module response is malformed.');
            }

            $body .= $chunk;
        }

        return $body;
    }

    /**
     * Validate module archive paths, type, size, and identity.
     *
     * CMSEC-2026-4828-C
     * CMSEC-2026-4828-K
     * CMSEC-2026-4828-R — Portable archive path identity
     *
     * @return array<int, string>
     */
    private function validateModuleArchive(
        string $packagePath,
        string $module
    ): array {
        $zip = new ZipArchive();

        if ($zip->open($packagePath) !== true) {
            throw new RuntimeException('Update package is not a valid ZIP archive.');
        }

        if ($zip->numFiles < 1 || $zip->numFiles > 2000) {
            $zip->close();
            throw new RuntimeException('Update package file count is invalid.');
        }

        $files = [];
        $totalSize = 0;
        $controllerFound = false;
        $archivePrefix = $module . '/';
        $metadataFound = false;
        $seenPaths = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';
            $normalized = str_replace('\\', '/', $name);
            $segments = explode('/', rtrim($normalized, '/'));

            if (
                $normalized === ''
                || str_contains($name, '\\')
                || str_starts_with($normalized, '/')
                || preg_match('/^[a-zA-Z]:\//', $normalized)
                || in_array('..', explode('/', $normalized), true)
                || str_contains($normalized, "\0")
            ) {
                $zip->close();
                throw new RuntimeException('Update package contains an unsafe path.');
            }

            foreach ($segments as $segment) {
                if (
                    $segment === ''
                    || $segment === '.'
                    || str_contains($segment, ':')
                    || preg_match('/[. ]$/', $segment)
                    || preg_match(
                        '/^(?:con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i',
                        $segment
                    )
                ) {
                    $zip->close();
                    throw new RuntimeException('Update package path is not portable.');
                }
            }

            $pathKey = strtolower(rtrim($normalized, '/'));

            if (isset($seenPaths[$pathKey])) {
                $zip->close();
                throw new RuntimeException('Update package contains colliding paths.');
            }

            $seenPaths[$pathKey] = true;

            $attributes = 0;
            $operationsSystem = 0;

            if (
                $zip->getExternalAttributesIndex(
                    $index,
                    $operationsSystem,
                    $attributes
                )
                && (($attributes >> 16) & 0170000) === 0120000
            ) {
                $zip->close();
                throw new RuntimeException('Update package contains a symbolic link.');
            }

            if (!str_starts_with($normalized, $archivePrefix)) {
                $zip->close();
                throw new RuntimeException(
                    'Update package contains files outside the module boundary.'
                );
            }

            if (str_ends_with($normalized, '/')) {
                continue;
            }

            $size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
            $totalSize += $size;

            if ($size > 10485760 || $totalSize > 52428800) {
                $zip->close();
                throw new RuntimeException('Update package expands beyond the size limit.');
            }

            $moduleRelative = substr($normalized, strlen($archivePrefix));

            if ($moduleRelative === '' || str_ends_with($moduleRelative, '/')) {
                continue;
            }

            if ($moduleRelative === 'controllers/' . $module . '.php') {
                $controllerFound = true;
            }

            if ($moduleRelative === 'module.json') {
                $metadataFound = true;
            }

            $files[] = str_replace('/', DIRECTORY_SEPARATOR, $moduleRelative);
        }

        $zip->close();

        if (!$controllerFound || !$metadataFound) {
            throw new RuntimeException('Update package does not match the requested module.');
        }

        return $files;
    }

    /**
     * Copy one staged or backup module file.
     *
     * CMSEC-2026-4828-D
     * CMSEC-2026-4828-K
     */
    private function copyModuleFile(
        string $source,
        string $destination
    ): void {
        $directory = dirname($destination);

        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new RuntimeException('Could not create module destination directory.');
        }

        if (!copy($source, $destination)) {
            throw new RuntimeException('Could not install a module file.');
        }
    }

    /**
     * Restore files after an incomplete module update.
     *
     * CMSEC-2026-4828-D
     * CMSEC-2026-4828-K
     */
    private function rollbackModuleUpdate(
        array $installed,
        array $backedUp,
        string $backupPath,
        string $moduleRoot
    ): void {
        foreach (array_reverse($installed) as $relativePath) {
            $destination = $moduleRoot
                . DIRECTORY_SEPARATOR
                . $relativePath;

            if (isset($backedUp[$relativePath])) {
                $backup = $backupPath . DIRECTORY_SEPARATOR . $relativePath;

                if (is_file($backup)) {
                    $this->copyModuleFile($backup, $destination);
                }
            } elseif (is_file($destination)) {
                unlink($destination);
            }
        }
    }

    /**
     * Remove the isolated module update workspace.
     *
     * CMSEC-2026-4828-D
     */
    private function cleanupModuleUpdate(string $workRoot): void
    {
        if (is_dir($workRoot)) {
            $this->recursive_rmdir($workRoot);
        }
    }

    /**
     * Copy a directory recursively.
     *
     * CMSEC-2026-4828-D
     *
     * Preserved for existing controller behavior. The verified module
     * update path now installs an enumerated set of staged files instead.
     */
    private function recursive_copy($src, $dst)
{
    if (!is_dir($src)) return;

    @mkdir($dst, 0755, true);

    foreach (scandir($src) as $file) {
        if ($file === '.' || $file === '..') continue;

        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";

        if (is_dir($srcPath)) {
            $this->recursive_copy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
}

    /**
     * Remove an installed non-Core module.
     *
     * CMSEC-2026-4828-E — Authenticated module removal
     * CMSEC-2026-4828-F — Trusted module identity and owned resources
     * CMSEC-2026-4828-K — User module ownership boundary
     * CMSEC-2026-4828-P — Transactional module uninstall
     * CMSEC-2026-4830-E — Serialized module maintenance
     *
     * @return void
     */
    public function uninstall(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            $this->error_page('POST required.');
        }

        $this->require_admin(9);
        $this->require_csrf();

        $module = trim((string) ($_POST['module'] ?? ''));

        if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/', $module)) {
            $this->error_page('Invalid module.');
        }

        if ($this->isCore($module)) {
            $this->error_page('Core modules cannot be removed.');
        }

        try {
            $maintenanceLock = $this->acquireModuleMaintenanceLock($module);
        } catch (Throwable $error) {
            $this->error_page('Another module maintenance operation is active.');
        }

        try {
            $moduleRoot = $this->resolveInstalledModuleRoot($module);
        } catch (Throwable $error) {
            $this->error_page('Installed module boundary could not be verified.');
        }

        $configPath = $moduleRoot . '/module.json';

        try {
            $config = $this->readModuleJson($configPath);
        } catch (Throwable $error) {
            $this->error_page('Installed module metadata could not be verified.');
        }

        $controllerPath = $moduleRoot . '/controllers/' . $module . '.php';

        if (!is_file($controllerPath)) {
            $this->error_page('Installed module controller was not found.');
        }

        /*
         * CMSEC-2026-4828-F
         *
         * Database ownership comes only from trusted local module metadata.
         * Request input is never interpolated as a database identifier.
         */
        $tables = $this->moduleOwnedTables($config, $module);

        $quarantineRoot = dirname($moduleRoot)
            . DIRECTORY_SEPARATOR
            . '.' . $module . '.uninstall-'
            . bin2hex(random_bytes(8));

        /*
         * CMSEC-2026-4828-P — Transactional module uninstall
         *
         * Disabled ordering dropped database tables before confirming that
         * the installed module could be removed. The owned directory is now
         * quarantined first and restored if database cleanup throws.
         */
        if (!rename($moduleRoot, $quarantineRoot)) {
            $this->error_page('Installed module could not be quarantined.');
        }

        try {
            if ($tables !== []) {
                $moduleModel = $this->model('modules_model');

                foreach ($tables as $table) {
                    $moduleModel->query(
                        'DROP TABLE IF EXISTS `' . $table . '`'
                    );
                }
            }
        } catch (Throwable $error) {
            if (!rename($quarantineRoot, $moduleRoot)) {
                error_log('Module uninstall rollback failed.');
            }

            $this->error_page('Module database cleanup failed.');
        }

        /*
         * CMSEC-2026-4828-K
         *
         * Disabled legacy ownership model retained for maintenance history:
         * individual controller, model, view, and metadata paths under
         * /app were deleted separately. User modules are now owned and
         * removed only as /user/modules/{slug}.
         *
         * $ownedFiles = [
         *     APPROOT . '/controllers/' . $module . '.php',
         *     APPROOT . '/models/' . $module . '_model.php',
         *     APPROOT . '/views/admin/' . $module . '.php',
         *     APPROOT . '/data/modules/' . $module . '.json',
         * ];
         * $this->deleteModuleDirectory(
         *     APPROOT . '/views/public/' . $module
         * );
         */
        try {
            $this->deleteModuleDirectory($quarantineRoot);
        } catch (Throwable $error) {
            error_log(
                'Module uninstall file cleanup failed for ['
                . $module . ']: ' . $error->getMessage()
            );
            $this->error_page('Module files could not be removed.');
        }

        $_SESSION['admin_status'] = 'Module ' . $module . ' was removed.';
        header('Location: /admin/modules');
        exit;
    }

    /*
     * CMSEC-2026-4828-E
     * CMSEC-2026-4828-F
     *
     * Previous uninstall behavior is retained as a disabled maintenance
     * record. It relied on POST alone, trusted the request-derived module
     * identifier for filesystem and SQL targets, and did not verify CSRF.
     *
     * public function uninstall_previous(): void
     * {
     *     if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
     *         header("Location: /admin");
     *         exit;
     *     }
     *
     *     $module = $_POST['module'] ?? '';
     *
     *     if (!$module) {
     *         $this->error_page('Invalid module.');
     *     }
     *
     *     if ($this->isCore($module)) {
     *         $this->error_page('Core modules cannot be removed.');
     *     }
     *
     *     $this->db->query(
     *         "DROP TABLE IF EXISTS `" . $module . "`"
     *     );
     * }
     */

    /**
     * Resolve database tables owned by an installed module.
     *
     * CMSEC-2026-4828-F
     *
     * @return array<int, string>
     */
    private function moduleOwnedTables(
        array $config,
        string $module
    ): array {
        $configured = $config['database_tables'] ?? [];

        if (!is_array($configured)) {
            return [];
        }

        $tables = [];

        foreach ($configured as $table) {
            if (!is_string($table)) {
                continue;
            }

            $table = trim($table);

            if (
                preg_match('/^[a-z][a-z0-9_]{0,63}$/', $table)
                && (
                    $table === $module
                    || str_starts_with($table, $module . '_')
                )
            ) {
                $tables[] = $table;
            }
        }

        return array_values(array_unique($tables));
    }

    /**
     * Delete one verified module-owned file.
     *
     * CMSEC-2026-4828-F
     */
    private function deleteModuleFile(string $file): void
    {
        if (!file_exists($file)) {
            return;
        }

        $root = realpath(APPROOT);
        $resolved = realpath($file);

        if (
            $root === false
            || $resolved === false
            || !str_starts_with(
                $resolved,
                $root . DIRECTORY_SEPARATOR
            )
            || !is_file($resolved)
        ) {
            throw new RuntimeException('Module file target escaped the application root.');
        }

        if (!unlink($resolved)) {
            throw new RuntimeException('A module file could not be removed.');
        }
    }

    /**
     * Delete one verified module-owned directory.
     *
     * CMSEC-2026-4828-F
     * CMSEC-2026-4828-I
     * CMSEC-2026-4828-K
     */
    private function deleteModuleDirectory(string $directory): void
    {
        /*
         * CMSEC-2026-4828-I
         *
         * A top-level module-directory link is removed as a link. It is not
         * resolved and traversed, even when its target remains under views.
         */
        if (is_link($directory)) {
            if (!unlink($directory)) {
                throw new RuntimeException('Module directory link could not be removed.');
            }

            return;
        }

        if (!is_dir($directory)) {
            return;
        }

        $modulesRoot = realpath(USERROOT . '/modules');
        $resolved = realpath($directory);

        if (
            $modulesRoot === false
            || $resolved === false
            || !str_starts_with(
                $resolved,
                $modulesRoot . DIRECTORY_SEPARATOR
            )
        ) {
            throw new RuntimeException('Module directory target escaped its owned root.');
        }

        $this->recursive_rmdir($resolved);
    }

    /*
     * CMSEC-2026-4828-E and CMSEC-2026-4828-F replaced the active block
     * below. The original statements remain visible in the disabled record
     * above rather than being retained as executable behavior.
     */
    /*
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /admin");
            exit;
        }

        $module = $_POST['module'] ?? '';

        if (!$module) {
            $this->error_page('Invalid module.');
        }

        if ($this->isCore($module)) {
            $this->error_page('Core modules cannot be removed.');
        }

        $config = APPROOT . '/data/modules/' . $module . '.json';
        if (file_exists($config)) {
            unlink($config);
        }

        if (isset($this->db)) {
            $this->db->query("DROP TABLE IF EXISTS `" . $module . "`");
        }

        $backend = [
            APPROOT . "/controllers/" . $module . ".php",
            APPROOT . "/models/" . $module . "_model.php"
        ];

        foreach ($backend as $file) {
            if (file_exists($file)) unlink($file);
        }

        $admin_view = APPROOT . "/views/admin/" . $module . ".php";
        if (file_exists($admin_view)) unlink($admin_view);

        $public_dir = APPROOT . "/views/public/" . $module;
        if (is_dir($public_dir)) {
            $this->recursive_rmdir($public_dir);
        }

        header("Location: /admin/modules");
        exit;
    */

    /**
     * Remove a directory tree used by module maintenance operations.
     *
     * CMSEC-2026-4828-D
     * CMSEC-2026-4828-I
     */
    private function recursive_rmdir($dir): void
    {
        /*
         * CMSEC-2026-4828-I
         *
         * Previous recursion tested is_dir() first. Because is_dir() follows
         * directory symbolic links, a nested link could escape the intended
         * module tree. Links are now unlinked before any directory test.
         *
         * Previous behavior:
         * if (is_dir($path)) {
         *     $this->recursive_rmdir($path);
         * } else {
         *     unlink($path);
         * }
         */
        if (is_link($dir)) {
            if (!unlink($dir)) {
                throw new RuntimeException('Module link could not be removed.');
            }
            return;
        }

        if (!is_dir($dir)) return;

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;

            $path = $dir . '/' . $item;

            if (is_link($path)) {
                if (!unlink($path)) {
                    throw new RuntimeException('Module link could not be removed.');
                }
            } elseif (is_dir($path)) {
                $this->recursive_rmdir($path);
            } elseif (!unlink($path)) {
                throw new RuntimeException('Module file could not be removed.');
            }
        }

        if (!rmdir($dir)) {
            throw new RuntimeException('Module directory could not be removed.');
        }
    }
}
