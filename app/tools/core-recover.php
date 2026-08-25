<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
// Standalone Core recovery utility. It deliberately does not load app/bootstrap.php.
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Core recovery is command-line only.\n");
    exit(1);
}

$options = getopt('', ['root:', 'confirm']);
$rootInput = $options['root'] ?? dirname(__DIR__, 2);
$root = realpath(is_string($rootInput) ? $rootInput : '');

if (!is_string($root) || !isset($options['confirm']) || dirname($root) === $root || !is_file($root . '/app/core/config.php')) {
    fwrite(STDERR, "Usage: php app/tools/core-recover.php --root=/path/to/chaos-mvc --confirm\n");
    exit(1);
}

$stateRoot = $root . '/.chaos-update';
$lockPath = $stateRoot . '/core.lock';
$lock = is_file($lockPath) ? json_decode((string) file_get_contents($lockPath), true) : null;
$operationId = is_array($lock) && preg_match('/^[a-f0-9]{32}$/', (string) ($lock['operation_id'] ?? ''))
    ? $lock['operation_id']
    : null;
$backupRoot = $operationId !== null && is_dir($stateRoot . '/operations/' . $operationId . '/backup')
    ? $stateRoot . '/operations/' . $operationId . '/backup'
    : $stateRoot . '/rollback';
$backupStatePath = $backupRoot . '/backup-state.json';
$backupManifestPath = $backupRoot . '/installed-manifest.json';

if (!is_file($backupStatePath) || !is_file($backupManifestPath)) {
    fwrite(STDERR, "No recovery backup is available.\n");
    exit(2);
}

$backupState = json_decode((string) file_get_contents($backupStatePath), true);
$backupManifest = json_decode((string) file_get_contents($backupManifestPath), true);
$currentManifestPath = $root . '/.chaos-core-manifest.json';
$currentManifest = is_file($currentManifestPath)
    ? json_decode((string) file_get_contents($currentManifestPath), true)
    : null;

// During an interrupted install the ownership marker still describes the old
// Core. Read the staged target manifest so recovery also removes new-only files.
if ($operationId !== null) {
    $journalPath = $stateRoot . '/operations/' . $operationId . '/journal.jsonl';
    $stagingId = null;

    if (is_file($journalPath)) {
        foreach (file($journalPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $entry = json_decode($line, true);

            if (is_array($entry) && ($entry['event'] ?? null) === 'update_started') {
                $candidate = $entry['data']['staging_id'] ?? null;

                if (is_string($candidate) && preg_match('/^[a-f0-9]{32}$/', $candidate)) {
                    $stagingId = $candidate;
                }
            }
        }
    }

    $targetManifestPath = $stagingId !== null
        ? $stateRoot . '/staging/' . $stagingId . '/core-manifest.json'
        : '';

    if (is_file($targetManifestPath)) {
        $targetManifest = json_decode((string) file_get_contents($targetManifestPath), true);

        if (is_array($targetManifest['files'] ?? null)) {
            $currentManifest = $targetManifest;
        }
    }
}

if (!is_array($backupState) || !is_array($backupManifest) || !is_array($backupState['files'] ?? null)) {
    fwrite(STDERR, "Recovery backup state is invalid.\n");
    exit(2);
}

$safePath = static function (string $path): bool {
    $segments = explode('/', $path);
    $normalized = strtolower($path);

    return $path !== '' && !str_contains($path, '\\') && !str_starts_with($path, '/')
        && !str_contains($path, ':') && !str_ends_with($path, '/')
        && !in_array('.', $segments, true) && !in_array('..', $segments, true)
        && $normalized !== 'app/core/config.php' && $normalized !== 'public'
        && !str_starts_with($normalized, 'public/') && $normalized !== '.chaos-update'
        && !str_starts_with($normalized, '.chaos-update/');
};

$configHash = hash_file('sha256', $root . '/app/core/config.php');

if (!is_string($configHash) || !hash_equals((string) ($backupState['config_sha256'] ?? ''), $configHash)) {
    fwrite(STDERR, "Protected configuration changed; automatic recovery refused.\n");
    exit(3);
}

$oldPaths = [];

foreach ($backupState['files'] as $file) {
    $path = is_array($file) ? ($file['path'] ?? null) : null;
    $hash = is_array($file) ? ($file['sha256'] ?? null) : null;
    $size = is_array($file) ? ($file['size'] ?? null) : null;
    $source = is_string($path) ? $backupRoot . '/files/' . str_replace('/', DIRECTORY_SEPARATOR, $path) : '';

    if (!is_string($path) || !$safePath($path) || !is_string($hash) || !is_int($size)
        || !is_file($source) || filesize($source) !== $size
        || !hash_equals($hash, (string) hash_file('sha256', $source))) {
        fwrite(STDERR, "Recovery backup verification failed.\n");
        exit(3);
    }

    $oldPaths[$path] = true;
}

foreach ($backupState['files'] as $file) {
    $path = $file['path'];
    $source = $backupRoot . '/files/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $destination = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $directory = dirname($destination);

    if (!is_dir($directory) && !mkdir($directory, 0750, true)) {
        fwrite(STDERR, "Could not create a recovery destination.\n");
        exit(4);
    }

    $temporary = $directory . '/.' . basename($destination) . '.cli-recover.' . bin2hex(random_bytes(6));

    if (!copy($source, $temporary) || !hash_equals($file['sha256'], (string) hash_file('sha256', $temporary))) {
        @unlink($temporary);
        fwrite(STDERR, "Could not stage a recovery file.\n");
        exit(4);
    }

    if (!@rename($temporary, $destination)) {
        if (is_file($destination) && !@unlink($destination)) {
            @unlink($temporary);
            fwrite(STDERR, "Could not replace a Core file.\n");
            exit(4);
        }

        if (!@rename($temporary, $destination)) {
            @unlink($temporary);
            fwrite(STDERR, "Could not replace a Core file.\n");
            exit(4);
        }
    }
}

if (is_array($currentManifest['files'] ?? null)) {
    foreach ($currentManifest['files'] as $file) {
        $path = is_array($file) ? ($file['path'] ?? null) : null;

        if (!is_string($path) || !$safePath($path) || isset($oldPaths[$path])) {
            continue;
        }

        $destination = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);

        if (is_file($destination) && !@unlink($destination)) {
            fwrite(STDERR, "Could not remove a new Core file during recovery.\n");
            exit(4);
        }
    }
}

$manifestTemporary = $currentManifestPath . '.recover.' . bin2hex(random_bytes(6));
$manifestRaw = json_encode($backupManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($manifestRaw === false || file_put_contents($manifestTemporary, $manifestRaw . PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "Could not restore installed Core ownership state.\n");
    exit(4);
}

if (!@rename($manifestTemporary, $currentManifestPath)) {
    @unlink($manifestTemporary);
    fwrite(STDERR, "Could not restore installed Core ownership state.\n");
    exit(4);
}

@unlink($stateRoot . '/maintenance.json');

if ($operationId !== null) {
    @unlink($lockPath);
}

fwrite(STDOUT, 'Restored Chaos MVC Core ' . ($backupManifest['version'] ?? 'unknown') . ".\n");
exit(0);
/* [End AI:GPT-5.6 Sol] */
