<?php
declare(strict_types=1);

/**
 * Update installation-owned themes. No module dispatch, SQL, or Core updating.
 * Transport and verification mirror the module release contract; theme
 * signatures use their own domain separator and theme identity.
 */
/* [AI:GPT-5.6 Sol | 2026-09-04 14:04:16 UTC] */
final class theme_updater
{
    /** Local discovery only, including damaged themes which may need recovery. */
    public function installed(): array
    {
        $root = $this->root();
        $slugs = [];
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $slug = basename($directory);
            if ($this->validSlug($slug) && !is_link($directory)) $slugs[$slug] = true;
        }
        foreach (glob($root . '/.*.previous', GLOB_ONLYDIR) ?: [] as $directory) {
            if (preg_match('/^\.([a-z][a-z0-9_-]{0,62})\.previous$/', basename($directory), $match)
                && !is_link($directory)) $slugs[$match[1]] = true;
        }
        $items = [];
        foreach (array_keys($slugs) as $slug) {
            $config = [];
            $metadataError = null;
            try { $config = $this->metadata($this->directory($slug), $slug); }
            catch (Throwable $error) { $metadataError = $error->getMessage(); }
            $hasSource = false;
            $sourceError = null;
            try {
                $this->parseThemeRemoteUrl((string) ($config['update_url'] ?? ''));
                $hasSource = true;
            } catch (Throwable $error) {
                if (!empty($config['update_url'])) $sourceError = $error->getMessage();
            }
            $items[] = [
                'slug' => $slug,
                'name' => (string) ($config['name'] ?? $slug),
                'version' => (string) ($config['version'] ?? ''),
                'author' => (string) ($config['creator'] ?? $config['author'] ?? ''),
                'description' => (string) ($config['description'] ?? ''),
                'has_update_source' => $hasSource,
                'metadata_error' => $metadataError,
                'update_source_error' => $sourceError,
                'domain' => (string) ($config['domain'] ?? ''),
                'certified' => filter_var($config['certified'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No',
                'rollback_available' => is_dir($root . '/.' . $slug . '.previous')
                    && !is_link($root . '/.' . $slug . '.previous'),
            ];
        }
        usort($items, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $items;
    }

    public function check(string $slug): array
    {
        $lock = $this->lock($slug);
        try {
            $config = $this->metadata($this->directory($slug), $slug);
            $manifest = $this->manifest($config, $slug, false);
            return [
                'success' => true,
                'current_version' => $config['version'],
                'available_version' => $manifest['version'],
                'update_available' => version_compare($manifest['version'], $config['version'], '>'),
            ];
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    public function update(string $slug): array
    {
        $lock = $this->lock($slug);
        $workspace = null;
        try {
            $config = $this->metadata($this->directory($slug), $slug);
            $manifest = $this->manifest($config, $slug, true);
            if (!version_compare($manifest['version'], $config['version'], '>')) {
                return ['success' => true, 'version' => $config['version'], 'message' => 'Already up to date.'];
            }
            $workspace = $this->root() . '/.' . $slug . '.work-' . bin2hex(random_bytes(8));
            if (!mkdir($workspace, 0700)) throw new RuntimeException('Could not create theme staging workspace.');
            $package = $this->downloadThemeResource($manifest['download'], 26214400);
            $zipPath = $workspace . '/package.zip';
            if (file_put_contents($zipPath, $package, LOCK_EX) !== strlen($package)) {
                throw new RuntimeException('Could not stage theme package.');
            }
            if (!hash_equals(strtolower($manifest['sha256']), (string) hash_file('sha256', $zipPath))) {
                throw new RuntimeException('Theme package integrity check failed.');
            }
            $files = $this->validateThemeArchive($zipPath, $slug);
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) throw new RuntimeException('Could not open theme package.');
            try {
                if (!$zip->extractTo($workspace)) throw new RuntimeException('Could not extract theme package.');
            } finally { $zip->close(); }
            $incoming = $workspace . '/' . $slug;
            $staged = $this->metadata($incoming, $slug);
            if ($staged['version'] !== $manifest['version']) {
                throw new RuntimeException('Packaged theme version does not match signed release.');
            }
            if (!empty($staged['database_tables']) || !empty($staged['migrations'])) {
                throw new RuntimeException('Theme updates do not execute database lifecycle operations.');
            }
            foreach ($files as $file) {
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
                    // Parse without executing theme code.
                    token_get_all((string) file_get_contents($incoming . '/' . $file), TOKEN_PARSE);
                }
            }
            foreach (['update_url', 'package_hosts', 'signing'] as $key) {
                if (array_key_exists($key, $config)) $staged[$key] = $config[$key];
                else unset($staged[$key]);
            }
            $json = json_encode($staged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($incoming . '/theme.json', $json . PHP_EOL, LOCK_EX) !== strlen($json . PHP_EOL)) {
                throw new RuntimeException('Could not preserve installed theme trust.');
            }
            $message = $this->activate($slug, $incoming, true);
            return ['success' => true, 'version' => $manifest['version'], 'message' => $message];
        } finally {
            if ($workspace !== null) $this->cleanup($workspace);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function rollback(string $slug): array
    {
        $lock = $this->lock($slug);
        try {
            $previous = $this->root() . '/.' . $slug . '.previous';
            $config = $this->metadata($previous, $slug);
            return [
                'success' => true,
                'version' => $config['version'],
                'message' => $this->activate($slug, $previous, false),
            ];
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    /** Exchange complete directories. Exceptions during activation restore the old files. */
    private function activate(string $slug, string $incoming, bool $retain): string
    {
        $root = $this->root();
        $live = $root . '/' . $slug;
        $previous = $root . '/.' . $slug . '.previous';
        $backup = $root . '/.' . $slug . '.backup-' . bin2hex(random_bytes(8));
        if (is_link($live) || (file_exists($live) && (!is_dir($live) || dirname(realpath($live)) !== $root))) {
            throw new RuntimeException('Installed theme boundary is invalid.');
        }
        $hadLive = is_dir($live);
        if ($hadLive && !rename($live, $backup)) {
            throw new RuntimeException('Could not back up installed theme. Live files were not changed.');
        }
        $activated = false;
        try {
            if (!rename($incoming, $live)) throw new RuntimeException('Could not activate theme files.');
            $activated = true;
            if ($retain && $hadLive) {
                $this->removeTree($previous);
                if (!rename($backup, $previous)) throw new RuntimeException('Could not retain previous theme files.');
            }
        } catch (Throwable $error) {
            try {
                if ($activated && !rename($live, $incoming)) throw new RuntimeException('Could not move failed theme aside.');
                if ($hadLive && !rename($backup, $live)) throw new RuntimeException('Could not restore previous theme.');
            } catch (Throwable $restoreError) {
                throw new RuntimeException('Theme restoration failed. Preserve recovery directories and inspect before retrying.', 0, $restoreError);
            }
            throw new RuntimeException($error->getMessage() . ' Previous filesystem state restored.', 0, $error);
        }
        $warning = '';
        if (!$retain && $hadLive && !$this->cleanup($backup)) $warning = ' Replaced-file cleanup requires attention.';
        $this->invalidatePhp($live);
        return ($retain
            ? 'Theme updated. One previous filesystem version is available for rollback.'
            : 'Previous theme files restored.') . $warning;
    }

    private function manifest(array $config, string $slug, bool $verify): array
    {
        $url = (string) ($config['update_url'] ?? '');
        $raw = $this->downloadThemeResource($url, 1048576);
        $manifest = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || ($manifest['theme'] ?? '') !== $slug
            || !is_string($manifest['version'] ?? null)
            || !preg_match('/^[0-9]+(?:\.[0-9]+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $manifest['version'])) {
            throw new RuntimeException('Theme release identity or version is invalid.');
        }
        // Like modules, discovery compares versions. Installation verifies all artifacts.
        if (!$verify || !version_compare($manifest['version'], $config['version'], '>')) return $manifest;
        $download = $manifest['download'] ?? null;
        $sha = $manifest['sha256'] ?? null;
        if (!is_string($download) || !is_string($sha) || !preg_match('/^[a-f0-9]{64}$/i', $sha)) {
            throw new RuntimeException('Theme release package URL or SHA-256 is invalid.');
        }
        if (!$this->isAuthorizedThemePackageHost($url, $download, $config)) {
            throw new RuntimeException('Theme package host is not authorized by installed metadata.');
        }
        $this->verifyThemeReleaseSignature($manifest, $config, $slug, $manifest['version'], $download, strtolower($sha));
        return $manifest;
    }

    private function validSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_-]{0,62}$/', $slug);
    }

    private function root(): string
    {
        $path = USERROOT . '/themes';
        $root = realpath($path);
        if ($root === false || !is_dir($root) || is_link($path)) throw new RuntimeException('Theme root is unavailable.');
        return $root;
    }

    private function directory(string $slug): string
    {
        if (!$this->validSlug($slug)) throw new RuntimeException('Invalid theme identifier.');
        return $this->root() . '/' . $slug;
    }

    private function metadata(string $directory, string $slug): array
    {
        $root = $this->root();
        $real = realpath($directory);
        $file = $directory . '/theme.json';
        if ($real === false || is_link($directory) || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)
            || !is_file($file) || is_link($file) || filesize($file) > 1048576) {
            throw new RuntimeException('Theme metadata is missing or outside its owned directory.');
        }
        $config = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($config) || ($config['theme'] ?? '') !== $slug
            || !is_string($config['version'] ?? null)
            || !preg_match('/^[0-9]+(?:\.[0-9]+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $config['version'])) {
            throw new RuntimeException('Theme metadata identity or version is invalid.');
        }
        return $config;
    }

    /** @return resource */
    private function lock(string $slug)
    {
        $this->directory($slug);
        $directory = $this->root() . '/.locks';
        if (!is_dir($directory) && !mkdir($directory, 0700)) throw new RuntimeException('Theme lock directory is unavailable.');
        if (is_link($directory) || dirname(realpath($directory)) !== $this->root()) {
            throw new RuntimeException('Theme lock boundary is invalid.');
        }
        $file = $directory . '/' . $slug . '.lock';
        if (is_link($file)) throw new RuntimeException('Theme lock file is invalid.');
        $lock = fopen($file, 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('Another theme maintenance operation is active.');
        }
        @chmod($file, 0600);
        return $lock;
    }

    private function removeTree(string $path): void
    {
        // Only internally generated maintenance paths under this installation's themes.
        $root = $this->root();
        $parent = realpath(dirname($path));
        if ($parent === false || ($parent !== $root && !str_starts_with($parent, $root . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Theme cleanup escaped its owned root.');
        }
        if (is_link($path)) {
            if (!unlink($path)) throw new RuntimeException('Could not remove theme link.');
            return;
        }
        if (!file_exists($path)) return;
        if (!is_dir($path)) {
            if (!unlink($path)) throw new RuntimeException('Could not remove theme file.');
            return;
        }
        foreach (scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') $this->removeTree($path . '/' . $entry);
        }
        if (!rmdir($path)) throw new RuntimeException('Could not remove theme directory.');
    }

    private function cleanup(string $path): bool
    {
        try { $this->removeTree($path); return true; }
        catch (Throwable $error) {
            error_log('Theme update cleanup failed: ' . $error->getMessage());
            return false;
        }
    }

    private function invalidatePhp(string $directory): void
    {
        if (!function_exists('opcache_invalidate')) return;
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isLink() && $file->isFile() && strtolower($file->getExtension()) === 'php') {
                    @opcache_invalidate($file->getPathname(), true);
                }
            }
        } catch (Throwable $error) {
            error_log('Theme opcode cache invalidation requires attention.');
        }
    }

    private function downloadThemeResource(
        string $url,
        int $maximumBytes
    ): string {
        $remote = $this->parseThemeRemoteUrl($url);
        $addresses = $this->resolvePublicThemeAddresses($remote['host']);
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
            throw new RuntimeException('Secure theme connection failed.');
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
            . "User-Agent: Chaos-MVC-Theme-Updater/1\r\n\r\n";

        $written = 0;
        $requestLength = strlen($request);

        while ($written < $requestLength) {
            $result = fwrite($resource, substr($request, $written));

            if ($result === false || $result === 0) {
                fclose($resource);
                throw new RuntimeException('Theme request could not be sent.');
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
            throw new RuntimeException('Theme server returned an invalid response.');
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
                throw new RuntimeException('Theme response headers exceed the limit.');
            }

            $line = rtrim($line, "\r\n");

            if ($line === '') {
                break;
            }

            if (!str_contains($line, ':')) {
                fclose($resource);
                throw new RuntimeException('Theme server returned invalid headers.');
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        if ($status < 200 || $status > 299) {
            fclose($resource);
            throw new RuntimeException('Theme server returned a non-success status.');
        }

        if (isset($headers['location'])) {
            fclose($resource);
            throw new RuntimeException('Theme server attempted a redirect.');
        }

        if (
            isset($headers['content-length'])
            && (
                !ctype_digit($headers['content-length'])
                || (int) $headers['content-length'] > $maximumBytes
            )
        ) {
            fclose($resource);
            throw new RuntimeException('Remote theme resource exceeds the size limit.');
        }

        $transferEncoding = strtolower($headers['transfer-encoding'] ?? '');
        $data = $transferEncoding === 'chunked'
            ? $this->readChunkedThemeBody($resource, $maximumBytes)
            : stream_get_contents($resource, $maximumBytes + 1);

        $timedOut = stream_get_meta_data($resource)['timed_out'] ?? false;
        fclose($resource);

        if (!is_string($data) || $data === '') {
            throw new RuntimeException('Remote theme resource was empty.');
        }

        if ($timedOut) {
            throw new RuntimeException('Remote theme request timed out.');
        }

        if (strlen($data) > $maximumBytes) {
            throw new RuntimeException('Remote theme resource exceeds the size limit.');
        }

        return $data;
    }

    private function isAuthorizedThemePackageHost(
        string $manifestUrl,
        string $packageUrl,
        array $config
    ): bool {
        $manifest = $this->parseThemeRemoteUrl($manifestUrl);
        $package = $this->parseThemeRemoteUrl($packageUrl);
        $allowed = [$manifest['host']];
        $configured = $config['package_hosts'] ?? [];

        if (is_array($configured)) {
            foreach ($configured as $host) {
                if (!is_string($host)) {
                    continue;
                }

                try {
                    $remote = $this->parseThemeRemoteUrl(
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

    private function parseThemeRemoteUrl(string $url): array
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Remote theme URL is invalid.');
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
            throw new RuntimeException('Remote theme URL must use HTTPS on port 443.');
        }

        $host = strtolower(trim((string) $parts['host'], '[]'));

        if (
            filter_var($host, FILTER_VALIDATE_IP) === false
            && !preg_match(
                '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                $host
            )
        ) {
            throw new RuntimeException('Remote theme hostname is invalid.');
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

    private function resolvePublicThemeAddresses(string $host): array
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
            throw new RuntimeException('Remote theme hostname could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (!$this->isPublicThemeAddress($address)) {
                throw new RuntimeException(
                    'Remote theme hostname resolves to a non-public address.'
                );
            }
        }

        return $addresses;
    }

    private function isPublicThemeAddress(string $address): bool
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

    private function readChunkedThemeBody(
        $resource,
        int $maximumBytes
    ): string {
        $body = '';

        while (true) {
            $line = fgets($resource, 8192);

            if (!is_string($line)) {
                throw new RuntimeException('Chunked theme response ended unexpectedly.');
            }

            $sizeText = trim(explode(';', $line, 2)[0]);

            if ($sizeText === '' || !ctype_xdigit($sizeText)) {
                throw new RuntimeException('Chunked theme response is invalid.');
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
                            'Theme response trailers exceed the limit.'
                        );
                    }

                    if (rtrim($trailer, "\r\n") === '') {
                        break;
                    }
                }

                break;
            }

            if (strlen($body) + $size > $maximumBytes) {
                throw new RuntimeException('Remote theme resource exceeds the size limit.');
            }

            $chunk = '';

            while (strlen($chunk) < $size) {
                $part = fread($resource, $size - strlen($chunk));

                if (!is_string($part) || $part === '') {
                    throw new RuntimeException('Chunked theme response ended unexpectedly.');
                }

                $chunk .= $part;
            }

            if (fread($resource, 2) !== "\r\n") {
                throw new RuntimeException('Chunked theme response is malformed.');
            }

            $body .= $chunk;
        }

        return $body;
    }

    private function verifyThemeReleaseSignature(
        array $manifest,
        array $config,
        string $theme,
        string $version,
        string $downloadUrl,
        string $sha256
    ): void {
        require_once __DIR__ . '/release_signature.php';
        $statement = implode("\n", [
            'CHAOS-MVC-THEME-RELEASE',
            'theme=' . $theme,
            'version=' . $version,
            'download=' . $downloadUrl,
            'sha256=' . $sha256,
            'key_id=' . (string) ($manifest['key_id'] ?? ''),
        ]);

        \release_signature::verify(
            is_array($config['signing'] ?? null) ? $config['signing'] : [],
            (string) ($manifest['key_id'] ?? ''),
            (string) ($manifest['signature'] ?? ''),
            $statement
        );
    }

    private function validateThemeArchive(
        string $packagePath,
        string $theme
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
        $partsFound = [];
        $archivePrefix = $theme . '/';
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
                    'Update package contains files outside the theme boundary.'
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

            $themeRelative = substr($normalized, strlen($archivePrefix));

            if ($themeRelative === '' || str_ends_with($themeRelative, '/')) {
                continue;
            }

            foreach (['head', 'nav', 'foot'] as $part) {
                if ($themeRelative === $part . '.php' || $themeRelative === 'inc/' . $part . '.php') {
                    $partsFound[$part] = true;
                }
            }

            if ($themeRelative === 'theme.json') {
                $metadataFound = true;
            }

            $files[] = str_replace('/', DIRECTORY_SEPARATOR, $themeRelative);
        }

        $zip->close();

        if (count($partsFound) !== 3 || !$metadataFound) {
            throw new RuntimeException('Update package does not match the requested theme.');
        }

        return $files;
    }
}
/* [End AI:GPT-5.6 Sol] */
