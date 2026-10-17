<?php

declare(strict_types=1);

/**
 * Fail-closed environment and filesystem qualification for installation.
 */
final class installer_preflight
{
    /**
     * Identify the environment and demonstrate every mandatory capability.
     *
     * @param array<string, mixed>|null $server
     * @return array<string, mixed>
     */
    public static function qualify(
        ?array $server = null,
        ?string $osFamily = null,
        ?string $sapi = null
    ): array
    {
        $server ??= $_SERVER;
        $osFamily ??= PHP_OS_FAMILY;
        $sapi ??= PHP_SAPI;
        $software = trim((string) ($server['SERVER_SOFTWARE'] ?? ''));
        $edition = trim((string) ($server['LSWS_EDITION'] ?? getenv('LSWS_EDITION') ?: ''));
        $webServer = self::identifyWebServer($software, $edition, $sapi);
        $isWindows = strcasecmp($osFamily, 'Windows') === 0;
        $isWebRequest = !in_array($sapi, ['cli', 'phpdbg'], true)
            && isset($server['REQUEST_METHOD'], $server['SERVER_PROTOCOL']);
        $requestPath = parse_url((string) ($server['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $identified = $isWindows || $webServer !== null;

        $environmentErrors = [];
        if (!$identified) {
            $environmentErrors[] = 'The operating environment could not be positively identified as Windows, Apache2, nginx, LiteSpeed, or OpenLiteSpeed.';
        }
        if (!$isWebRequest) {
            $environmentErrors[] = 'The installer is not running through a validated web-server PHP request.';
        }
        if ($requestPath !== '/install') {
            $environmentErrors[] = 'Environment validation must be run through the /install route.';
        }
        if ($webServer === 'Apache2' && $sapi === 'apache2handler'
            && function_exists('apache_get_modules')
            && !in_array('mod_rewrite', apache_get_modules(), true)) {
            $environmentErrors[] = 'Apache2 is active, but mod_rewrite is not available to the running PHP environment.';
        }
        if (in_array($webServer, ['Apache2', 'LiteSpeed', 'OpenLiteSpeed'], true)
            && !is_readable(PUBROOT . '/.htaccess')) {
            $environmentErrors[] = 'The public rewrite configuration could not be read for the identified server environment.';
        }

        $environment = [
            'operating_system' => $osFamily,
            'web_server' => $webServer ?? 'Unknown',
            'server_software' => $software !== '' ? $software : 'Unavailable',
            'php_version' => PHP_VERSION,
            'php_sapi' => $sapi,
            'runtime_user' => self::runtimeUser(),
        ];

        /*
         * Unknown or invalid environments stop here. Filesystem probes create
         * short-lived test resources, so they are not attempted until the
         * Foundation environment gate has passed.
         */
        if ($environmentErrors !== []) {
            return [
                'passed' => false,
                'environment' => $environment,
                'environment_errors' => $environmentErrors,
                'requirements' => [],
            ];
        }

        $root = dirname(APPROOT);
        $requirements = [
            self::fileRequirement('Installer schema', APPROOT . '/install/schema.sql', false),
            self::fileRequirement('Core configuration', APPROOT . '/core/config.php', true),
            self::directoryRequirement('Application data', APPROOT . '/data'),
            self::directoryRequirement('Core updater data', APPROOT . '/data/updater'),
            self::directoryRequirement('Installer lock and logs', LOG_PATH),
            self::directoryRequirement('User pages', USERROOT . '/pages'),
            self::directoryRequirement('User modules', USERROOT . '/modules'),
            self::directoryRequirement('User themes', USERROOT . '/themes'),
            self::directoryRequirement('Generated public resources', PUBROOT),
            self::directoryRequirement('Media uploads', PUBROOT . '/uploads'),
            self::directoryRequirement('Core updater workspace', $root . '/tmp/updater'),
            self::directoryRequirement('Core updater rollback', $root . '/backups/updater'),
            self::directoryRequirement('PHP temporary workspace', sys_get_temp_dir(), false),
        ];

        if (!class_exists('mysqli')) {
            $requirements[] = [
                'resource' => 'PHP mysqli extension',
                'required' => 'AVAILABLE',
                'passed' => false,
                'result' => 'The mysqli extension is not available.',
            ];
        }

        $capabilitiesPassed = true;
        foreach ($requirements as $requirement) {
            if (!$requirement['passed']) {
                $capabilitiesPassed = false;
                break;
            }
        }

        return [
            'passed' => $environmentErrors === [] && $capabilitiesPassed,
            'environment' => $environment,
            'environment_errors' => $environmentErrors,
            'requirements' => $requirements,
        ];
    }

    /**
     * Create required runtime directories only after qualification succeeds.
     */
    public static function prepareRuntimeDirectories(): void
    {
        $root = dirname(APPROOT);
        $directories = [
            APPROOT . '/data',
            APPROOT . '/data/updater',
            LOG_PATH,
            USERROOT . '/pages',
            USERROOT . '/modules',
            USERROOT . '/themes',
            PUBROOT . '/uploads',
            $root . '/tmp/updater',
            $root . '/backups/updater',
        ];

        foreach ($directories as $directory) {
            if (is_link($directory)) {
                throw new RuntimeException('A required installer directory is a symbolic link.');
            }
            if (!is_dir($directory)
                && !mkdir($directory, 0775, true)
                && !is_dir($directory)) {
                throw new RuntimeException('A required installer directory could not be created.');
            }
        }
    }

    private static function identifyWebServer(string $software, string $edition, string $sapi): ?string
    {
        $evidence = strtolower($software . ' ' . $edition);
        if (str_contains($evidence, 'openlitespeed')) {
            return 'OpenLiteSpeed';
        }
        if (str_contains($evidence, 'litespeed') || str_contains(strtolower($sapi), 'litespeed')) {
            return 'LiteSpeed';
        }
        if (str_contains($evidence, 'apache') || $sapi === 'apache2handler') {
            return 'Apache2';
        }
        if (str_contains($evidence, 'nginx')) {
            return 'nginx';
        }
        return null;
    }

    /** @return array<string, mixed> */
    private static function fileRequirement(string $label, string $path, bool $writable): array
    {
        if (is_link($path)) {
            return self::requirement($label, $writable ? 'WRITE' : 'READ', false, 'Symbolic links are not accepted.');
        }
        if (is_file($path)) {
            if (!$writable) {
                return self::requirement($label, 'READ', is_readable($path), is_readable($path) ? 'Readable.' : 'Read access failed.');
            }
            $handle = @fopen($path, 'ab');
            if ($handle === false) {
                return self::requirement($label, 'WRITE', false, 'Write access could not be opened.');
            }
            fclose($handle);
            return self::requirement($label, 'WRITE', true, 'Write access demonstrated without changing file content.');
        }
        if (!$writable) {
            return self::requirement($label, 'READ', false, 'Required file is missing.');
        }
        $probe = self::probeCreatablePath(dirname($path));
        return self::requirement($label, 'CREATE / WRITE', $probe['passed'], $probe['result']);
    }

    /** @return array<string, mixed> */
    private static function directoryRequirement(string $label, string $path, bool $allowMissing = true): array
    {
        if (is_link($path)) {
            return self::requirement($label, 'CREATE / WRITE / RENAME / DELETE', false, 'Symbolic links are not accepted.');
        }
        if (is_dir($path)) {
            $probe = self::probeDirectory($path);
            return self::requirement($label, 'CREATE / WRITE / RENAME / DELETE', $probe['passed'], $probe['result']);
        }
        if (!$allowMissing) {
            return self::requirement($label, 'CREATE / WRITE / RENAME / DELETE', false, 'Required directory is missing.');
        }
        $probe = self::probeCreatablePath(dirname($path));
        return self::requirement($label, 'CREATE / WRITE / RENAME / DELETE', $probe['passed'], $probe['passed'] ? 'Directory is absent; creation capability was demonstrated in its parent.' : $probe['result']);
    }

    /** @return array{passed: bool, result: string} */
    private static function probeCreatablePath(string $parent): array
    {
        while (!is_dir($parent)) {
            $next = dirname($parent);
            if ($next === $parent) {
                return ['passed' => false, 'result' => 'No existing parent directory could be qualified.'];
            }
            $parent = $next;
        }
        if (is_link($parent)) {
            return ['passed' => false, 'result' => 'The nearest existing parent is a symbolic link.'];
        }
        return self::probeDirectory($parent);
    }

    /** @return array{passed: bool, result: string} */
    private static function probeDirectory(string $directory): array
    {
        try {
            $token = bin2hex(random_bytes(12));
        } catch (Throwable $error) {
            return ['passed' => false, 'result' => 'A secure qualification token could not be created.'];
        }

        $first = $directory . DIRECTORY_SEPARATOR . '.chaos-install-' . $token;
        $second = $first . '-renamed';
        $payload = "chaos-installer-preflight\n";

        try {
            if (@file_put_contents($first, $payload, LOCK_EX) !== strlen($payload)) {
                return ['passed' => false, 'result' => 'Create or write capability failed.'];
            }
            if (@file_get_contents($first) !== $payload) {
                return ['passed' => false, 'result' => 'Read-back capability failed.'];
            }
            if (!@rename($first, $second)) {
                return ['passed' => false, 'result' => 'Rename capability failed.'];
            }
            if (!@unlink($second)) {
                return ['passed' => false, 'result' => 'Delete capability failed.'];
            }
            return ['passed' => true, 'result' => 'Create, write, read, rename, and delete capabilities demonstrated.'];
        } finally {
            if (is_file($first) || is_link($first)) {
                @unlink($first);
            }
            if (is_file($second) || is_link($second)) {
                @unlink($second);
            }
        }
    }

    /** @return array<string, mixed> */
    private static function requirement(string $resource, string $required, bool $passed, string $result): array
    {
        return compact('resource', 'required', 'passed', 'result');
    }

    private static function runtimeUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $account = @posix_getpwuid(posix_geteuid());
            if (is_array($account) && is_string($account['name'] ?? null)) {
                return $account['name'];
            }
        }
        return get_current_user() ?: 'Unavailable';
    }
}
