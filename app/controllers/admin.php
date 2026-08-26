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
    public function index($params = []) 
    {
        if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] < 7) {
            header("Location: /auth/login");
            exit;
        }

        $slug = $params[0] ?? null;

        if ($slug && method_exists($this, $slug) && $slug !== 'index') {
            $this->$slug($params);
            return;
        }

        $path = APPROOT . '/controllers/' . $slug . '.php';
        if ($slug && file_exists($path)) {
            require_once $path;

            if (class_exists($slug)) {
                $controller = new $slug();

                if (method_exists($controller, 'admin')) {
                    $controller->admin($params);
                    return;
                }
            }
        }

        $this->view('admin/index');
    }
    
    /**
     * Install an authenticated and verified module update.
     *
     * CMSEC-2026-4828-A — POST and CSRF request intent
     * CMSEC-2026-4828-B — Manifest and package integrity
     * CMSEC-2026-4828-C — Safe archive and module identity
     * CMSEC-2026-4828-D — Staging, backup, and rollback
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

        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $module)) {
            $this->respondModuleUpdate(false, null, 'Invalid module.');
        }

        if ($this->isCore($module)) {
            $this->respondModuleUpdate(false, null, 'Core modules cannot be updated here.');
        }

        $configPath = APPROOT . '/data/modules/' . $module . '.json';

        try {
            $config = $this->readModuleJson($configPath);
        } catch (Throwable $error) {
            $this->respondModuleUpdate(false, null, $error->getMessage());
        }

        $originalConfig = file_get_contents($configPath);
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
        $backupPath = $workRoot . DIRECTORY_SEPARATOR . 'backup';
        $installed = [];
        $backedUp = [];

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
            $downloadUrl = (string) ($manifest['download'] ?? '');
            $expectedHash = strtolower((string) ($manifest['sha256'] ?? ''));

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

            foreach ($files as $relativePath) {
                $source = $stagePath . DIRECTORY_SEPARATOR . $relativePath;
                $destination = APPROOT . DIRECTORY_SEPARATOR . $relativePath;

                if (!is_file($source)) {
                    continue;
                }

                if (file_exists($destination)) {
                    $backup = $backupPath . DIRECTORY_SEPARATOR . $relativePath;
                    $this->copyModuleFile($destination, $backup);
                    $backedUp[$relativePath] = true;
                }

                $this->copyModuleFile($source, $destination);
                $installed[] = $relativePath;
            }

            $config['version'] = $new;

            $encodedConfig = json_encode(
                $config,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );

            if (
                $encodedConfig === false
                || file_put_contents(
                    $configPath,
                    $encodedConfig . PHP_EOL,
                    LOCK_EX
                ) === false
            ) {
                throw new RuntimeException('Could not update module metadata.');
            }

            $this->cleanupModuleUpdate($workRoot);
            $this->respondModuleUpdate(true, $new, null);
        } catch (Throwable $error) {
            try {
                $this->rollbackModuleUpdate(
                    $installed,
                    $backedUp,
                    $backupPath
                );

                if (is_string($originalConfig)) {
                    file_put_contents(
                        $configPath,
                        $originalConfig,
                        LOCK_EX
                    );
                }
            } catch (Throwable $rollbackError) {
                error_log(
                    'Module update rollback failed: '
                    . $rollbackError->getMessage()
                );
            }

            $this->cleanupModuleUpdate($workRoot);
            error_log('Module update failed: ' . $error->getMessage());
            $this->respondModuleUpdate(false, null, $error->getMessage());
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
        $allowedFiles = [
            'controllers/' . $module . '.php',
            'models/' . $module . '_model.php',
            'views/admin/' . $module . '.php',
        ];
        $publicPrefix = 'views/public/' . $module . '/';

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';
            $normalized = str_replace('\\', '/', $name);

            if (
                $normalized === ''
                || str_starts_with($normalized, '/')
                || preg_match('/^[a-zA-Z]:\//', $normalized)
                || in_array('..', explode('/', $normalized), true)
                || str_contains($normalized, "\0")
            ) {
                $zip->close();
                throw new RuntimeException('Update package contains an unsafe path.');
            }

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

            if (str_ends_with($normalized, '/')) {
                continue;
            }

            $allowed = in_array($normalized, $allowedFiles, true)
                || str_starts_with($normalized, $publicPrefix);

            if (!$allowed) {
                $zip->close();
                throw new RuntimeException(
                    'Update package contains files outside the module boundary.'
                );
            }

            $size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
            $totalSize += $size;

            if ($size > 10485760 || $totalSize > 52428800) {
                $zip->close();
                throw new RuntimeException('Update package expands beyond the size limit.');
            }

            if ($normalized === 'controllers/' . $module . '.php') {
                $controllerFound = true;
            }

            $files[] = str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        }

        $zip->close();

        if (!$controllerFound) {
            throw new RuntimeException('Update package does not match the requested module.');
        }

        return $files;
    }

    /**
     * Copy one staged or backup module file.
     *
     * CMSEC-2026-4828-D
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
     */
    private function rollbackModuleUpdate(
        array $installed,
        array $backedUp,
        string $backupPath
    ): void {
        foreach (array_reverse($installed) as $relativePath) {
            $destination = APPROOT . DIRECTORY_SEPARATOR . $relativePath;

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

        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $module)) {
            $this->error_page('Invalid module.');
        }

        if ($this->isCore($module)) {
            $this->error_page('Core modules cannot be removed.');
        }

        $configPath = APPROOT . '/data/modules/' . $module . '.json';

        try {
            $config = $this->readModuleJson($configPath);
        } catch (Throwable $error) {
            $this->error_page('Installed module metadata could not be verified.');
        }

        $controllerPath = APPROOT . '/controllers/' . $module . '.php';

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

        if ($tables !== []) {
            $moduleModel = $this->model('modules_model');

            foreach ($tables as $table) {
                $moduleModel->query(
                    'DROP TABLE IF EXISTS `' . $table . '`'
                );
            }
        }

        $ownedFiles = [
            $controllerPath,
            APPROOT . '/models/' . $module . '_model.php',
            APPROOT . '/views/admin/' . $module . '.php',
            $configPath,
        ];

        foreach ($ownedFiles as $file) {
            $this->deleteModuleFile($file);
        }

        $this->deleteModuleDirectory(
            APPROOT . '/views/public/' . $module
        );

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

        $publicRoot = realpath(APPROOT . '/views/public');
        $resolved = realpath($directory);

        if (
            $publicRoot === false
            || $resolved === false
            || !str_starts_with(
                $resolved,
                $publicRoot . DIRECTORY_SEPARATOR
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
            unlink($dir);
            return;
        }

        if (!is_dir($dir)) return;

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;

            $path = $dir . '/' . $item;

            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->recursive_rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
