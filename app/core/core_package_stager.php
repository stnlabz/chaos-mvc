<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_package_stager
{
    private string $installationRoot;
    private string $stateDirectory;
    private $fetcher;

    public function __construct(string $installationRoot, string $stateDirectory, ?callable $fetcher = null)
    {
        $this->installationRoot = rtrim($installationRoot, '/\\');
        $this->stateDirectory = rtrim($stateDirectory, '/\\');
        $this->fetcher = $fetcher;
    }

    /**
     * Download, validate, extract, and preflight an authenticated Core offer.
     *
     * @param array<string, mixed> $offer
     * @return array<string, mixed>
     */
    public function stage(array $offer): array
    {
        if (!extension_loaded('zip')) {
            return $this->failure('preflight', 'core_zip_unavailable', 'PHP ZIP support is unavailable.');
        }

        $required = ['version', 'package_url', 'package_sha256', 'package_size', 'manifest_sha256'];

        foreach ($required as $field) {
            if (!array_key_exists($field, $offer)) {
                return $this->failure('staging', 'core_offer_incomplete', 'The authenticated Core offer is incomplete.');
            }
        }

        if (
            !is_string($offer['version']) || !preg_match('/^\d+\.\d+\.\d+$/', $offer['version']) ||
            !is_string($offer['package_url']) || !$this->isApprovedPackageUrl($offer['package_url']) ||
            !is_string($offer['package_sha256']) || !preg_match('/^[a-f0-9]{64}$/', strtolower($offer['package_sha256'])) ||
            !is_int($offer['package_size']) || $offer['package_size'] < 1 ||
            !is_string($offer['manifest_sha256']) || !preg_match('/^[a-f0-9]{64}$/', strtolower($offer['manifest_sha256']))
        ) {
            return $this->failure('staging', 'core_offer_invalid', 'The authenticated Core offer is invalid.');
        }

        $operationId = bin2hex(random_bytes(16));
        $stagingRoot = $this->stateDirectory . '/staging/' . $operationId;
        $archivePath = $stagingRoot . '/package.zip';
        $filesRoot = $stagingRoot . '/files';

        if (!@mkdir($filesRoot, 0750, true)) {
            return $this->failure('staging', 'core_staging_create_failed', 'The Core staging directory could not be created.');
        }

        try {
            $package = $this->downloadPackage($offer['package_url'], $offer['package_size']);

            if (strlen($package) !== $offer['package_size']) {
                throw new RuntimeException('core_package_size_mismatch|The Core package size does not match the signed offer.');
            }

            if (!hash_equals(strtolower($offer['package_sha256']), hash('sha256', $package))) {
                throw new RuntimeException('core_package_digest_mismatch|The Core package digest does not match the signed offer.');
            }

            if (file_put_contents($archivePath, $package, LOCK_EX) !== strlen($package)) {
                throw new RuntimeException('core_package_write_failed|The Core package could not be written to staging.');
            }

            unset($package);
            $validated = $this->validateAndExtract(
                $archivePath,
                $filesRoot,
                $offer['version'],
                strtolower($offer['manifest_sha256'])
            );
            $preflight = $this->preflight($validated['manifest'], $validated['expanded_size']);

            if (!$preflight['success']) {
                throw new RuntimeException($preflight['error_code'] . '|' . $preflight['message']);
            }

            $state = [
                'operation_id' => $operationId,
                'phase' => 'staged',
                'target_version' => $offer['version'],
                'package_sha256' => strtolower($offer['package_sha256']),
                'manifest_sha256' => strtolower($offer['manifest_sha256']),
                'file_count' => count($validated['manifest']['files']),
                'expanded_size' => $validated['expanded_size'],
                'staged_at' => gmdate('c')
            ];
            $statePath = $stagingRoot . '/state.json';
            $encodedState = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($encodedState === false || file_put_contents($statePath, $encodedState . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('core_stage_state_failed|Staged Core state could not be recorded.');
            }

            return [
                'success' => true,
                'outcome' => 'package_staged',
                'phase' => 'staged',
                'target_version' => $offer['version'],
                'operation_id' => $operationId,
                'file_count' => $state['file_count'],
                'expanded_size' => $state['expanded_size']
            ];
        } catch (Throwable $exception) {
            $this->removeTree($stagingRoot);
            [$code, $message] = array_pad(explode('|', $exception->getMessage(), 2), 2, 'Core package staging failed.');

            return $this->failure('staging', $code, $message);
        }
    }

    private function downloadPackage(string $url, int $expectedSize): string
    {
        if (is_callable($this->fetcher)) {
            $result = ($this->fetcher)($url, $expectedSize);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 60,
                    'ignore_errors' => false,
                    'follow_location' => 0,
                    'header' => "Accept: application/zip\r\nConnection: close\r\n"
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false
                ]
            ]);
            $result = @file_get_contents($url, false, $context, 0, $expectedSize + 1);
        }

        if (!is_string($result)) {
            throw new RuntimeException('core_package_download_failed|The Core package could not be downloaded.');
        }

        return $result;
    }

    /**
     * @return array{manifest: array<string, mixed>, expanded_size: int}
     */
    private function validateAndExtract(
        string $archivePath,
        string $filesRoot,
        string $version,
        string $expectedManifestHash
    ): array {
        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('core_package_zip_invalid|The Core package is not a valid ZIP archive.');
        }

        try {
            $root = 'chaos-mvc-' . $version . '/';
            $manifestEntry = $root . 'core-manifest.json';
            $manifestEntryCount = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $candidate = $zip->statIndex($index);

                if (($candidate['name'] ?? null) === $manifestEntry) {
                    $manifestEntryCount++;
                }
            }

            if ($manifestEntryCount !== 1) {
                throw new RuntimeException('core_manifest_count_invalid|The Core archive must contain exactly one manifest.');
            }

            $manifestRaw = $zip->getFromName($manifestEntry);

            if (!is_string($manifestRaw) || !hash_equals($expectedManifestHash, hash('sha256', $manifestRaw))) {
                throw new RuntimeException('core_manifest_digest_mismatch|The Core manifest digest is invalid.');
            }

            $manifest = json_decode($manifestRaw, true);

            if (!is_array($manifest) || ($manifest['schema'] ?? null) !== 1 || ($manifest['version'] ?? null) !== $version) {
                throw new RuntimeException('core_manifest_invalid|The Core manifest is invalid.');
            }

            $files = $manifest['files'] ?? null;

            if (!is_array($files) || $files === []) {
                throw new RuntimeException('core_manifest_files_invalid|The Core manifest contains no files.');
            }

            $fileMap = [];
            $expandedSize = 0;

            foreach ($files as $file) {
                if (!is_array($file)) {
                    throw new RuntimeException('core_manifest_file_invalid|A Core manifest file entry is invalid.');
                }

                $path = $file['path'] ?? null;
                $hash = strtolower((string) ($file['sha256'] ?? ''));
                $size = $file['size'] ?? null;

                if (
                    !is_string($path) || !$this->isSafeCorePath($path) ||
                    !preg_match('/^[a-f0-9]{64}$/', $hash) ||
                    !is_int($size) || $size < 0 || isset($fileMap[$path])
                ) {
                    throw new RuntimeException('core_manifest_file_invalid|A Core manifest file entry is invalid.');
                }

                $fileMap[$path] = ['sha256' => $hash, 'size' => $size];
                $expandedSize += $size;
            }

            foreach (['app/bootstrap.php', 'app/core/version.php'] as $requiredPath) {
                if (!isset($fileMap[$requiredPath])) {
                    throw new RuntimeException('core_manifest_required_file_missing|The Core manifest is missing a required file.');
                }
            }

            $seen = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = (string) ($stat['name'] ?? '');

                if (
                    $name === '' || !str_starts_with($name, $root) ||
                    str_contains($name, '\\') || str_contains($name, '../') || str_contains($name, ':')
                ) {
                    throw new RuntimeException('core_archive_root_invalid|The Core archive has an invalid release root.');
                }

                $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes);
                $unixType = ($attributes >> 16) & 0170000;

                if ($unixType === 0120000) {
                    throw new RuntimeException('core_archive_link_rejected|Core packages may not contain symbolic links.');
                }

                if (str_ends_with($name, '/')) {
                    continue;
                }

                if ($name === $manifestEntry) {
                    continue;
                }

                $relativePath = substr($name, strlen($root));

                if (!isset($fileMap[$relativePath]) || isset($seen[$relativePath])) {
                    throw new RuntimeException('core_archive_unlisted_file|The Core archive contains an unlisted file.');
                }

                if ((int) ($stat['size'] ?? -1) !== $fileMap[$relativePath]['size']) {
                    throw new RuntimeException('core_archive_file_size_mismatch|A Core archive file has an invalid size.');
                }

                $contents = $zip->getFromIndex($index);

                if (!is_string($contents) || !hash_equals($fileMap[$relativePath]['sha256'], hash('sha256', $contents))) {
                    throw new RuntimeException('core_archive_file_digest_mismatch|A Core archive file digest is invalid.');
                }

                $destination = $filesRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                $destinationDirectory = dirname($destination);

                if (!is_dir($destinationDirectory) && !@mkdir($destinationDirectory, 0750, true)) {
                    throw new RuntimeException('core_archive_extract_failed|A Core staging directory could not be created.');
                }

                if (file_put_contents($destination, $contents, LOCK_EX) !== strlen($contents)) {
                    throw new RuntimeException('core_archive_extract_failed|A Core file could not be staged.');
                }

                $seen[$relativePath] = true;
            }

            if (count($seen) !== count($fileMap)) {
                throw new RuntimeException('core_archive_file_missing|The Core archive is missing a manifest file.');
            }

            if (file_put_contents($filesRoot . '/core-manifest.json', $manifestRaw, LOCK_EX) === false) {
                throw new RuntimeException('core_manifest_write_failed|The Core manifest could not be staged.');
            }

            return ['manifest' => $manifest, 'expanded_size' => $expandedSize];
        } finally {
            $zip->close();
        }
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private function preflight(array $manifest, int $expandedSize): array
    {
        $requiredSpace = ($expandedSize * 2) + 1048576;
        $freeSpace = @disk_free_space($this->stateDirectory);

        if (is_float($freeSpace) && $freeSpace < $requiredSpace) {
            return $this->failure('preflight', 'core_storage_insufficient', 'There is not enough space to stage and back up Core.');
        }

        foreach ($manifest['files'] as $file) {
            $path = (string) $file['path'];
            $destination = $this->installationRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $probe = file_exists($destination) ? $destination : dirname($destination);

            while (!file_exists($probe) && dirname($probe) !== $probe) {
                $probe = dirname($probe);
            }

            if (!is_writable($probe)) {
                return $this->failure('preflight', 'core_destination_not_writable', 'A Core destination is not writable.');
            }
        }

        return ['success' => true, 'phase' => 'preflight'];
    }

    private function isSafeCorePath(string $path): bool
    {
        $segments = explode('/', $path);

        if (
            $path === '' || str_contains($path, '\\') || str_starts_with($path, '/') ||
            str_contains($path, '../') || str_contains($path, ':') || str_ends_with($path, '/') ||
            in_array('.', $segments, true) || in_array('..', $segments, true)
        ) {
            return false;
        }

        $normalized = strtolower($path);

        return $normalized !== 'app/core/config.php'
            && $normalized !== '.chaos-update'
            && !str_starts_with($normalized, '.chaos-update/')
            && $normalized !== 'public'
            && !str_starts_with($normalized, 'public/');
    }

    private function isApprovedPackageUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return ($parts['scheme'] ?? '') === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'chaos-mvc.org'
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path) && !is_link($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(string $phase, string $code, string $message): array
    {
        return [
            'success' => false,
            'outcome' => 'failed_unchanged',
            'phase' => $phase,
            'error_code' => $code,
            'message' => $message
        ];
    }
}
/* [End AI:GPT-5.6 Sol] */
