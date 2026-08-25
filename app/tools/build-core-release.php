<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$options = getopt('', [
    'root::',
    'version:',
    'package:',
    'metadata:',
    'package-url:',
    'private-key:',
    'minimum-updater::',
    'installed-manifest::'
]);
$root = is_string($options['root'] ?? null) ? $options['root'] : dirname(__DIR__, 2);

require_once $root . '/app/core/core_updater.php';
require_once dirname(__DIR__) . '/api/core_release_builder.php';

try {
    $builder = new core_release_builder($root);
    $version = (string) ($options['version'] ?? '');

    if (is_string($options['installed-manifest'] ?? null)) {
        $builder->writeInstalledManifest($version, $options['installed-manifest']);
        fwrite(STDOUT, "Installed Core manifest written.\n");
        exit(0);
    }

    $builder->build(
        $version,
        (string) ($options['package'] ?? ''),
        (string) ($options['metadata'] ?? ''),
        (string) ($options['package-url'] ?? ''),
        (string) ($options['private-key'] ?? ''),
        (string) ($options['minimum-updater'] ?? $version)
    );
    fwrite(STDOUT, "Signed Core release built.\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
/* [End AI:GPT-5.6 Sol] */
