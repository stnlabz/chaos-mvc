<?php
declare(strict_types=1);

final class cdm_model
{
    private const MAX_SOURCE_BYTES = 1048576;
    private const MAX_ARTIFACT_BYTES = 5242880;

    /** @return array<string, array<int, array<string, mixed>>> */
    public function overview(): array
    {
        return [
            'Runtime' => $this->runtimeObservations(),
            'PHP Extensions' => $this->extensionObservations(),
            'Session' => $this->sessionObservations(),
            'Core Files' => $this->coreFileObservations(),
            'Runtime Paths' => $this->pathObservations(),
            'Generated Resources' => $this->artifactObservations(),
        ];
    }

    /**
     * Run bounded probes only after authenticated, CSRF-verified POST.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function capabilityProbes(): array
    {
        $filesystem = [];

        foreach ($this->runtimePaths() as $id => $path) {
            $filesystem[] = $this->probeDirectory($id, $path);
        }

        return [
            'Filesystem Operations' => $filesystem,
            'Logging' => [$this->probeLogging()],
            'Database' => [$this->probeDatabase()],
        ];
    }

    /** @return array<int, string> */
    public function modules(): array
    {
        $root = $this->modulesRoot();
        $names = [];

        foreach (new DirectoryIterator($root) as $entry) {
            if (!$entry->isDir() || $entry->isDot() || $entry->isLink()) {
                continue;
            }

            $name = $entry->getFilename();

            if (
                preg_match('/^[a-z][a-z0-9_]{0,62}$/', $name) === 1
                && is_file($entry->getPathname() . '/module.json')
                && !is_link($entry->getPathname() . '/module.json')
            ) {
                $names[] = $name;
            }
        }

        sort($names, SORT_STRING);
        return $names;
    }

    /** @return array<string, mixed> */
    public function diagnose(string $name): array
    {
        if (!in_array($name, $this->modules(), true)) {
            throw new InvalidArgumentException(
                'Select an installed module from the list.'
            );
        }

        $root = $this->modulesRoot();
        $directory = realpath($root . DIRECTORY_SEPARATOR . $name);

        if (
            $directory === false
            || dirname($directory) !== $root
            || is_link($root . DIRECTORY_SEPARATOR . $name)
        ) {
            throw new RuntimeException('Module directory is not accessible.');
        }

        $findings = [];
        $inspected = [];
        $metadataFile = $directory . DIRECTORY_SEPARATOR . 'module.json';
        $raw = $this->readBoundedRegularFile($metadataFile);
        $inspected[] = 'module.json';
        $config = json_decode($raw, true);

        if (!is_array($config) || json_last_error() !== JSON_ERROR_NONE) {
            $findings[] = $this->finding(
                'module.json.syntax',
                'CONFIRMED',
                'module.json:1',
                'Module metadata is not valid JSON.',
                json_last_error_msg(),
                'Correct the JSON syntax and validate module.json.'
            );

            return $this->moduleReport($name, $findings, $inspected);
        }

        $this->inspectManifest($name, $directory, $config, $findings, $inspected);
        $this->inspectControllerContract(
            $name,
            $directory,
            $config,
            $findings,
            $inspected
        );

        return $this->moduleReport($name, $findings, $inspected);
    }

    private function modulesRoot(): string
    {
        if (!defined('USERROOT')) {
            throw new RuntimeException('ChAoS MVC USERROOT is unavailable.');
        }

        $root = realpath(USERROOT . '/modules');

        if ($root === false || !is_dir($root) || is_link(USERROOT . '/modules')) {
            throw new RuntimeException(
                'Installed module directory could not be located.'
            );
        }

        return $root;
    }

    /** @return array<int, array<string, mixed>> */
    private function runtimeObservations(): array
    {
        $user = null;

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $record = posix_getpwuid(posix_geteuid());
            $user = is_array($record) ? ($record['name'] ?? null) : null;
        }

        return [
            $this->result(
                'runtime.chaos_version',
                defined('CHAOS_VERSION') ? 'OBSERVED' : 'UNVERIFIED',
                'Installed ChAoS MVC version',
                defined('CHAOS_VERSION') ? (string) CHAOS_VERSION : 'Version constant unavailable.'
            ),
            $this->result(
                'runtime.php',
                'OBSERVED',
                'PHP runtime',
                PHP_VERSION . ' (' . PHP_SAPI . ')'
            ),
            $this->result(
                'runtime.server',
                isset($_SERVER['SERVER_SOFTWARE']) ? 'OBSERVED' : 'UNVERIFIED',
                'Web-server telemetry',
                (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'Unavailable from this SAPI.'),
                'Server identity is telemetry and does not establish capability.'
            ),
            $this->result(
                'runtime.account',
                is_string($user) && $user !== '' ? 'OBSERVED' : 'UNVERIFIED',
                'Effective runtime account',
                is_string($user) && $user !== '' ? $user : 'Unavailable.'
            ),
            $this->result(
                'runtime.os',
                'OBSERVED',
                'Operating system',
                PHP_OS_FAMILY . ' / ' . php_uname('s')
            ),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function extensionObservations(): array
    {
        $required = [
            'curl', 'dom', 'fileinfo', 'filter', 'hash', 'json', 'libxml',
            'mysqli', 'openssl', 'pdo', 'pdo_mysql', 'session', 'SimpleXML',
            'zip',
        ];
        $results = [];

        foreach ($required as $extension) {
            $loaded = extension_loaded($extension);
            $results[] = $this->result(
                'extension.' . strtolower($extension),
                $loaded ? 'OBSERVED' : 'FAILED',
                'PHP extension: ' . $extension,
                $loaded ? 'Loaded by the active PHP runtime.' : 'Not loaded.'
            );
        }

        return $results;
    }

    /** @return array<int, array<string, mixed>> */
    private function sessionObservations(): array
    {
        $active = session_status() === PHP_SESSION_ACTIVE;
        $params = session_get_cookie_params();

        return [
            $this->result(
                'session.active',
                $active ? 'DEMONSTRATED' : 'FAILED',
                'Session initialization',
                $active ? 'The current authenticated request has an active session.' : 'No active session is available.'
            ),
            $this->result(
                'session.cookie',
                !empty($params['httponly']) && (($params['samesite'] ?? '') !== '')
                    ? 'OBSERVED'
                    : 'FAILED',
                'Session cookie controls',
                'HttpOnly=' . (!empty($params['httponly']) ? 'yes' : 'no')
                    . '; Secure=' . (!empty($params['secure']) ? 'yes' : 'no')
                    . '; SameSite=' . (string) ($params['samesite'] ?? 'unset'),
                'Secure depends on whether the current request is HTTPS.'
            ),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function coreFileObservations(): array
    {
        $required = [
            'bootstrap' => APPROOT . '/bootstrap.php',
            'router' => APPROOT . '/core/router.php',
            'controller' => APPROOT . '/core/controller.php',
            'model' => APPROOT . '/core/model.php',
            'error_handler' => APPROOT . '/core/error_handler.php',
            'version' => APPROOT . '/core/version.php',
            'configuration' => APPROOT . '/core/config.php',
            'admin' => APPROOT . '/controllers/admin.php',
            'updater' => APPROOT . '/controllers/updater.php',
            'schema' => APPROOT . '/install/schema.sql',
        ];
        $results = [];

        foreach ($required as $id => $path) {
            $regular = is_file($path) && !is_link($path);
            $results[] = $this->result(
                'core.' . $id,
                $regular ? 'OBSERVED' : 'FAILED',
                'Core file: ' . $id,
                $regular ? 'Required regular file is present.' : 'Required regular file is missing or linked.'
            );
        }

        return $results;
    }

    /** @return array<string, string> */
    private function runtimePaths(): array
    {
        return [
            'logs' => defined('LOG_PATH') ? LOG_PATH : dirname(APPROOT) . '/logs',
            'application_data' => APPROOT . '/data',
            'updater_data' => APPROOT . '/data/updater',
            'user_pages' => USERROOT . '/pages',
            'user_modules' => USERROOT . '/modules',
            'user_themes' => USERROOT . '/themes',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function pathObservations(): array
    {
        $results = [];

        foreach ($this->runtimePaths() as $id => $path) {
            $exists = is_dir($path) && !is_link($path);
            $results[] = $this->result(
                'path.' . $id,
                $exists ? 'OBSERVED' : 'FAILED',
                'Runtime path: ' . $id,
                $exists
                    ? 'Directory exists; PHP reports writable=' . (is_writable($path) ? 'yes' : 'no') . '.'
                    : 'Directory is missing or is a symbolic link.',
                'Permission and ownership observations do not prove effective file operations.'
            );
        }

        return $results;
    }

    /** @return array<int, array<string, mixed>> */
    private function artifactObservations(): array
    {
        $artifacts = [
            'site.json' => 'json',
            'sitemap.xml' => 'xml',
            'ror.xml' => 'xml',
            'rss.xml' => 'xml',
            'llms.txt' => 'text',
        ];
        $results = [];

        foreach ($artifacts as $name => $type) {
            $path = PUBROOT . '/' . $name;

            if (!is_file($path) || is_link($path)) {
                $results[] = $this->result(
                    'artifact.' . $name,
                    'UNVERIFIED',
                    'Generated resource: ' . $name,
                    'No regular generated file is present.'
                );
                continue;
            }

            $size = @filesize($path);

            if ($size === false || $size < 1 || $size > self::MAX_ARTIFACT_BYTES) {
                $results[] = $this->result(
                    'artifact.' . $name,
                    'FAILED',
                    'Generated resource: ' . $name,
                    'Generated file size is empty, unavailable, or exceeds the diagnostic bound.'
                );
                continue;
            }

            $content = @file_get_contents($path);
            $valid = is_string($content);

            if ($valid && $type === 'json') {
                json_decode($content, true);
                $valid = json_last_error() === JSON_ERROR_NONE;
            } elseif (
                $valid
                && $type === 'xml'
                && function_exists('simplexml_load_string')
            ) {
                $previous = libxml_use_internal_errors(true);
                $valid = simplexml_load_string($content) !== false;
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            } elseif ($valid && $type === 'xml') {
                $results[] = $this->result(
                    'artifact.' . $name,
                    'UNVERIFIED',
                    'Generated resource: ' . $name,
                    'SimpleXML is unavailable, so XML structure was not verified.'
                );
                continue;
            }

            $results[] = $this->result(
                'artifact.' . $name,
                $valid ? 'OBSERVED' : 'FAILED',
                'Generated resource: ' . $name,
                $valid ? 'Present, bounded, readable, and structurally valid.' : 'Present but unreadable or structurally invalid.'
            );
        }

        return $results;
    }

    /** @return array<string, mixed> */
    private function probeDirectory(string $id, string $path): array
    {
        if (!is_dir($path) || is_link($path)) {
            return $this->result(
                'filesystem.' . $id,
                'FAILED',
                'Filesystem capability: ' . $id,
                'Directory is missing or is a symbolic link.'
            );
        }

        $token = bin2hex(random_bytes(8));
        $first = $path . DIRECTORY_SEPARATOR . '.cdm-probe-' . $token;
        $second = $first . '.renamed';
        $content = 'cdm:' . $token;
        $handle = null;

        try {
            $handle = fopen($first, 'x+b');

            if ($handle === false) {
                throw new RuntimeException('Create operation failed.');
            }

            if (fwrite($handle, $content) !== strlen($content) || !fflush($handle)) {
                throw new RuntimeException('Write operation failed.');
            }

            rewind($handle);

            if (stream_get_contents($handle) !== $content) {
                throw new RuntimeException('Read verification failed.');
            }

            fclose($handle);
            $handle = null;

            if (!rename($first, $second)) {
                throw new RuntimeException('Rename operation failed.');
            }

            if (!unlink($second)) {
                throw new RuntimeException('Cleanup operation failed.');
            }

            return $this->result(
                'filesystem.' . $id,
                'DEMONSTRATED',
                'Filesystem capability: ' . $id,
                'Create, write, read, rename, and cleanup completed in the active web runtime.'
            );
        } catch (Throwable $error) {
            return $this->result(
                'filesystem.' . $id,
                'FAILED',
                'Filesystem capability: ' . $id,
                $error->getMessage(),
                'Inspect the effective web-runtime account and confinement policy.'
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            foreach ([$first, $second] as $temporary) {
                if (is_file($temporary) && !is_link($temporary)) {
                    @unlink($temporary);
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function probeLogging(): array
    {
        $destination = (string) ini_get('error_log');

        if ($destination === '') {
            return $this->result(
                'logging.delivery',
                'UNVERIFIED',
                'ChAoS log delivery',
                'PHP has no explicit error_log destination.'
            );
        }

        $marker = '[CDM diagnostic probe ' . bin2hex(random_bytes(6)) . ']';
        try {
            $before = is_file($destination) ? @filesize($destination) : 0;
            $accepted = error_log($marker);
            clearstatcache(true, $destination);
            $after = is_file($destination) ? @filesize($destination) : false;
        } catch (Throwable $error) {
            return $this->result(
                'logging.delivery',
                'FAILED',
                'ChAoS log delivery',
                'The controlled logging probe raised an error.',
                'Review the effective SAPI log destination and runtime capability.'
            );
        }

        if ($accepted && is_int($before) && is_int($after) && $after > $before) {
            return $this->result(
                'logging.delivery',
                'DEMONSTRATED',
                'ChAoS log delivery',
                'PHP accepted the probe and the configured log file grew.'
            );
        }

        return $this->result(
            'logging.delivery',
            $accepted ? 'OBSERVED' : 'FAILED',
            'ChAoS log delivery',
            $accepted
                ? 'PHP accepted the probe, but local-file delivery could not be demonstrated.'
                : 'PHP rejected the controlled logging probe.',
            'SAPI-managed logging may require verification in the web-server log.'
        );
    }

    /** @return array<string, mixed> */
    private function probeDatabase(): array
    {
        if (
            !class_exists('PDO')
            || !extension_loaded('pdo_mysql')
            || !defined('DB_HOST')
            || !defined('DB_NAME')
            || !defined('DB_USER')
            || !defined('DB_PASS')
        ) {
            return $this->result(
                'database.connection',
                'UNVERIFIED',
                'Database capability',
                'Required PDO MySQL capability or ChAoS configuration is unavailable.'
            );
        }

        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 3,
                ]
            );
            $statement = $pdo->prepare('SELECT 1 AS diagnostic_value');
            $statement->execute();
            $row = $statement->fetch();

            if (!is_array($row) || (int) ($row['diagnostic_value'] ?? 0) !== 1) {
                throw new RuntimeException('Prepared read did not return the expected value.');
            }

            return $this->result(
                'database.connection',
                'DEMONSTRATED',
                'Database capability',
                'Connection and prepared read completed. Server version: '
                    . (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . '.'
            );
        } catch (Throwable $error) {
            return $this->result(
                'database.connection',
                'FAILED',
                'Database capability',
                'Database connection or prepared read failed.',
                'Review the protected database configuration and server availability.'
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, array<string, mixed>> $findings
     * @param array<int, string> $inspected
     */
    private function inspectManifest(
        string $name,
        string $directory,
        array $config,
        array &$findings,
        array &$inspected
    ): void {
        foreach (['module', 'version', 'files', 'routes', 'database_tables'] as $field) {
            if (!array_key_exists($field, $config)) {
                $findings[] = $this->finding(
                    'manifest.required.' . $field,
                    'CONFIRMED',
                    'module.json',
                    'Required module metadata field is missing.',
                    'Missing field: ' . $field,
                    'Declare the field using the current module contract.'
                );
            }
        }

        if (($config['module'] ?? null) !== $name) {
            $findings[] = $this->finding(
                'manifest.identity',
                'CONFIRMED',
                'module.json',
                'Module identity does not match its installation directory.',
                'Directory=' . $name . '; manifest=' . (string) ($config['module'] ?? 'missing'),
                'Make the manifest module slug exactly match the directory slug.'
            );
        }

        if (array_key_exists('index_parameters', $config) && !is_bool($config['index_parameters'])) {
            $findings[] = $this->finding(
                'manifest.index_parameters',
                'CONFIRMED',
                'module.json',
                'index_parameters must be a boolean.',
                'Observed type: ' . get_debug_type($config['index_parameters']),
                'Use true or false for the top-level index_parameters field.'
            );
        }

        if (array_key_exists('routes', $config) && !is_array($config['routes'])) {
            $findings[] = $this->finding(
                'manifest.routes',
                'CONFIRMED',
                'module.json',
                'Module routes must be an array.',
                'Observed type: ' . get_debug_type($config['routes']),
                'Declare only explicitly routable controller methods.'
            );
        } elseif (isset($config['routes'])) {
            foreach ($config['routes'] as $route) {
                if (!is_string($route) || preg_match('/^[a-z][a-z0-9_]{0,62}$/', $route) !== 1) {
                    $findings[] = $this->finding(
                        'manifest.route_name',
                        'CONFIRMED',
                        'module.json',
                        'Module route name is invalid.',
                        'Every route must be a confined controller method identifier.',
                        'Remove or correct the invalid route declaration.'
                    );
                    break;
                }
            }
        }

        if (
            array_key_exists('database_tables', $config)
            && !is_array($config['database_tables'])
        ) {
            $findings[] = $this->finding(
                'manifest.database_tables',
                'CONFIRMED',
                'module.json',
                'database_tables must be an array.',
                'Observed type: ' . get_debug_type($config['database_tables']),
                'Declare an empty array or the module-owned table identifiers.'
            );
        } elseif (isset($config['database_tables'])) {
            foreach ($config['database_tables'] as $table) {
                if (
                    !is_string($table)
                    || preg_match('/^[a-z][a-z0-9_]*$/', $table) !== 1
                    || ($table !== $name && !str_starts_with($table, $name . '_'))
                ) {
                    $findings[] = $this->finding(
                        'manifest.table_ownership',
                        'CONFIRMED',
                        'module.json',
                        'Database table declaration is outside the module namespace.',
                        'Invalid ownership declaration detected.',
                        'Declare only the module table or tables prefixed with ' . $name . '_.'
                    );
                    break;
                }
            }
        }

        if (!array_key_exists('files', $config)) {
            return;
        }

        if (!is_array($config['files'])) {
            $findings[] = $this->finding(
                'manifest.files',
                'CONFIRMED',
                'module.json',
                'Declared files must be an array.',
                'Observed type: ' . get_debug_type($config['files']),
                'Declare the module-owned package files.'
            );
            return;
        }

        foreach (array_values(array_unique($config['files'], SORT_REGULAR)) as $relative) {
            if (!is_string($relative) || !$this->validRelativePath($relative)) {
                $findings[] = $this->finding(
                    'manifest.file_path',
                    'CONFIRMED',
                    'module.json',
                    'Declared module file path is unsafe.',
                    is_scalar($relative) ? (string) $relative : get_debug_type($relative),
                    'Use a portable relative path confined to the module directory.'
                );
                continue;
            }

            $candidate = $directory . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $resolved = realpath($candidate);

            if (
                $resolved === false
                || !is_file($resolved)
                || is_link($candidate)
                || !str_starts_with($resolved, $directory . DIRECTORY_SEPARATOR)
            ) {
                $findings[] = $this->finding(
                    'manifest.file_missing',
                    'CONFIRMED',
                    'module.json',
                    'Declared module file is missing or outside module ownership.',
                    $relative,
                    'Restore the file or correct the files declaration.'
                );
                continue;
            }

            $inspected[] = $relative;

            if (str_ends_with(strtolower($relative), '.php')) {
                $this->inspectPhpSyntax($resolved, $relative, $findings);
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, array<string, mixed>> $findings
     * @param array<int, string> $inspected
     */
    private function inspectControllerContract(
        string $name,
        string $directory,
        array $config,
        array &$findings,
        array &$inspected
    ): void {
        $relative = 'controllers/' . $name . '.php';
        $path = $directory . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (!is_file($path) || is_link($path)) {
            $findings[] = $this->finding(
                'controller.missing',
                'CONFIRMED',
                $relative,
                'Expected module controller is missing.',
                'No regular controller exists at the conventional module path.',
                'Restore the module controller using the installed module slug.'
            );
            return;
        }

        $source = $this->readBoundedRegularFile($path);
        $inspected[] = $relative;
        $indexMethod = $this->methodSource($source, 'index');
        $usesParameters = $indexMethod !== null
            && preg_match('/\$params\s*\[/', $indexMethod['source']) === 1;
        $acceptsParameters = ($config['index_parameters'] ?? false) === true;

        if ($usesParameters && !$acceptsParameters) {
            $line = $indexMethod['line']
                + $this->firstMatchingLine(
                    $indexMethod['source'],
                    '/\$params\s*\[/'
                ) - 1;
            $findings[] = $this->finding(
                'route.index_parameters',
                'POTENTIAL MISMATCH',
                $relative . ':' . $line,
                'Controller accesses route parameters while index_parameters is not enabled.',
                'Parameter access was observed, but the top-level manifest contract is not true.',
                'Enable index_parameters only if index() intentionally accepts path segments.'
            );
        }

    }

    /** @return array{source: string, line: int}|null */
    private function methodSource(string $source, string $method): ?array
    {
        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            $methodName = null;

            for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                $candidate = $tokens[$cursor];

                if (is_array($candidate) && $candidate[0] === T_STRING) {
                    $methodName = $candidate[1];
                    break;
                }

                if ($candidate === '(') {
                    break;
                }
            }

            if ($methodName !== $method) {
                continue;
            }

            $methodSource = '';
            $depth = 0;
            $opened = false;

            for ($cursor = $index; $cursor < $count; $cursor++) {
                $candidate = $tokens[$cursor];
                $text = is_array($candidate) ? $candidate[1] : $candidate;
                $methodSource .= $text;

                if ($candidate === '{') {
                    $opened = true;
                    $depth++;
                } elseif ($candidate === '}' && $opened) {
                    $depth--;

                    if ($depth === 0) {
                        return [
                            'source' => $methodSource,
                            'line' => $token[2],
                        ];
                    }
                } elseif ($candidate === ';' && !$opened) {
                    return null;
                }
            }

            return null;
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $findings */
    private function inspectPhpSyntax(
        string $path,
        string $relative,
        array &$findings
    ): void {
        try {
            token_get_all($this->readBoundedRegularFile($path), TOKEN_PARSE);
        } catch (ParseError $error) {
            $findings[] = $this->finding(
                'php.syntax',
                'CONFIRMED',
                $relative . ':' . $error->getLine(),
                'Declared PHP file contains a syntax error.',
                $error->getMessage(),
                'Correct the syntax at the reported line.'
            );
        }
    }

    private function readBoundedRegularFile(string $path): string
    {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Diagnostic source is not a regular file.');
        }

        $size = @filesize($path);

        if ($size === false || $size > self::MAX_SOURCE_BYTES) {
            throw new RuntimeException('Diagnostic source exceeds the inspection bound.');
        }

        $content = @file_get_contents($path);

        if (!is_string($content)) {
            throw new RuntimeException('Diagnostic source could not be read.');
        }

        return $content;
    }

    private function validRelativePath(string $path): bool
    {
        return $path !== ''
            && strlen($path) <= 255
            && $path[0] !== '/'
            && !str_contains($path, '\\')
            && !str_contains($path, ':')
            && preg_match('#(?:^|/)\.\.(?:/|$)#', $path) !== 1
            && preg_match('#^[A-Za-z0-9._/-]+$#', $path) === 1;
    }

    private function firstMatchingLine(string $source, string $pattern): int
    {
        foreach (explode("\n", $source) as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                return $index + 1;
            }
        }

        return 1;
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     * @param array<int, string> $inspected
     * @return array<string, mixed>
     */
    private function moduleReport(
        string $name,
        array $findings,
        array $inspected
    ): array {
        return [
            'module' => $name,
            'findings' => $findings,
            'inspected' => array_values(array_unique($inspected)),
            'note' => 'Inspection is bounded and does not execute module PHP. No finding does not establish module correctness.',
        ];
    }

    /** @return array<string, mixed> */
    private function result(
        string $id,
        string $status,
        string $summary,
        string $evidence,
        ?string $action = null
    ): array {
        return [
            'id' => $id,
            'status' => $status,
            'summary' => $summary,
            'evidence' => $evidence,
            'action' => $action,
            'checked_at' => gmdate('c'),
        ];
    }

    /** @return array<string, mixed> */
    private function finding(
        string $id,
        string $status,
        string $code,
        string $summary,
        string $evidence,
        string $repair
    ): array {
        return [
            'id' => $id,
            'route' => 'N/A',
            'code' => $code,
            'summary' => $summary,
            'evidence' => $evidence,
            'repair' => $repair,
            'status' => $status,
        ];
    }
}
