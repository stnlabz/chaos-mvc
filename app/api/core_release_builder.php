<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_release_builder
{
    private string $root;

    public function __construct(string $root)
    {
        $resolved = realpath($root);

        if (!is_string($resolved) || !is_dir($resolved . '/app/core')) {
            throw new InvalidArgumentException('Chaos MVC source root is invalid.');
        }

        $this->root = rtrim($resolved, '/\\');
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(string $version): array
    {
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidArgumentException('Core version must use x.y.z format.');
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = str_replace('\\', '/', substr($absolute, strlen($this->root) + 1));

            if (!$this->isCoreOwnedPath($relative)) {
                continue;
            }

            $files[] = [
                'path' => $relative,
                'sha256' => hash_file('sha256', $absolute),
                'size' => $file->getSize()
            ];
        }

        usort($files, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));
        $paths = array_column($files, null, 'path');

        foreach (['app/bootstrap.php', 'app/core/version.php', 'app/tools/core-recover.php'] as $required) {
            if (!isset($paths[$required])) {
                throw new RuntimeException("Required Core release file is missing: {$required}");
            }
        }

        $versionSource = (string) file_get_contents($this->root . '/app/core/version.php');

        if (!preg_match(
            "/define\\(\\s*['\"]CHAOS_VERSION['\"]\\s*,\\s*['\"]" . preg_quote($version, '/') . "['\"]\\s*\\)/",
            $versionSource
        )) {
            throw new RuntimeException('app/core/version.php does not match the requested release version.');
        }

        $migrations = [];
        $migrationPath = 'app/install/migrations/' . $version . '.sql';

        if (isset($paths[$migrationPath])) {
            $migrations[] = [
                'id' => $version,
                'path' => $migrationPath,
                'sha256' => $paths[$migrationPath]['sha256']
            ];
        }

        return [
            'schema' => 1,
            'version' => $version,
            'files' => $files,
            'migrations' => $migrations
        ];
    }

    /**
     * Build a complete signed Core release.
     *
     * @return array<string, mixed>
     */
    public function build(
        string $version,
        string $packagePath,
        string $metadataPath,
        string $packageUrl,
        string $privateKeyPath,
        string $minimumUpdaterVersion
    ): array {
        if (!extension_loaded('zip') || !extension_loaded('openssl')) {
            throw new RuntimeException('PHP ZIP and OpenSSL extensions are required.');
        }

        if (!$this->approvedUrl($packageUrl) || !is_file($privateKeyPath)) {
            throw new RuntimeException('Release destination or signing key is invalid.');
        }

        $manifest = $this->manifest($version);
        $manifestRaw = (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $packageDirectory = dirname($packagePath);
        $metadataDirectory = dirname($metadataPath);

        foreach ([$packageDirectory, $metadataDirectory] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0750, true)) {
                throw new RuntimeException('Release output directory could not be created.');
            }
        }

        if (file_exists($packagePath) || file_exists($metadataPath)) {
            throw new RuntimeException('Release output already exists.');
        }

        $zip = new ZipArchive();

        if ($zip->open($packagePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Core release ZIP could not be created.');
        }

        $archiveRoot = 'chaos-mvc-' . $version . '/';
        $zip->addFromString($archiveRoot . 'core-manifest.json', $manifestRaw);

        foreach ($manifest['files'] as $file) {
            if (!$zip->addFile($this->root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file['path']), $archiveRoot . $file['path'])) {
                $zip->close();
                @unlink($packagePath);
                throw new RuntimeException('A Core release file could not be packaged.');
            }
        }

        $zip->close();
        $metadata = [
            'version' => $version,
            'package_url' => $packageUrl,
            'package_sha256' => hash_file('sha256', $packagePath),
            'package_size' => filesize($packagePath),
            'released_at' => gmdate('c'),
            'minimum_updater_version' => $minimumUpdaterVersion,
            'manifest_sha256' => hash('sha256', $manifestRaw)
        ];
        $privateKey = openssl_pkey_get_private((string) file_get_contents($privateKeyPath));

        if ($privateKey === false || !openssl_sign(
            core_updater::signatureMessage($metadata),
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        )) {
            @unlink($packagePath);
            throw new RuntimeException('Core release metadata could not be signed.');
        }

        $metadata['signature'] = base64_encode($signature);
        $metadataRaw = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($metadataRaw === false || file_put_contents($metadataPath, $metadataRaw . PHP_EOL, LOCK_EX) === false) {
            @unlink($packagePath);
            @unlink($metadataPath);
            throw new RuntimeException('Core release metadata could not be written.');
        }

        return ['manifest' => $manifest, 'metadata' => $metadata];
    }

    /**
     * Write the protected ownership marker for a fresh or manually upgraded installation.
     */
    public function writeInstalledManifest(string $version, string $path): void
    {
        $manifest = $this->manifest($version);
        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false || file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Installed Core manifest could not be written.');
        }
    }

    private function isCoreOwnedPath(string $path): bool
    {
        $normalized = strtolower($path);
        $root = explode('/', $normalized, 2)[0];

        if (in_array($root, ['.git', 'public', 'logs', '.chaos-update'], true)) {
            return false;
        }

        if (
            $normalized === 'app/core/config.php' ||
            $normalized === '.chaos-core-manifest.json' ||
            $normalized === '.gitignore' ||
            $normalized === '.gitattributes' ||
            str_starts_with($normalized, 'app/data/modules/') ||
            str_ends_with($normalized, '.private.pem') ||
            str_ends_with($normalized, '.tmp')
        ) {
            return false;
        }

        return in_array($root, ['app', 'docs', 'tests'], true)
            || in_array($normalized, ['readme.md', '.htaccess'], true);
    }

    private function approvedUrl(string $url): bool
    {
        $parts = filter_var($url, FILTER_VALIDATE_URL) !== false ? parse_url($url) : [];

        return ($parts['scheme'] ?? null) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'chaos-mvc.org';
    }
}
/* [End AI:GPT-5.6 Sol] */
