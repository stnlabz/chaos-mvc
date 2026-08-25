<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
$root = dirname(__DIR__);
define('APPROOT', $root . '/app');
define('PUBROOT', $root . '/public');
define('URLROOT', 'https://example.test');

require_once APPROOT . '/lib/render_md.php';
require_once APPROOT . '/lib/trash_filter.php';
require_once APPROOT . '/core/controller.php';
require_once APPROOT . '/core/model.php';
require_once APPROOT . '/core/core_update_lock.php';
require_once APPROOT . '/core/core_package_stager.php';
require_once APPROOT . '/core/core_updater.php';
require_once APPROOT . '/controllers/admin.php';

$failures = [];

$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$renderer = new render_md();
$unsafeLink = $renderer->markdown('[unsafe](javascript:alert(1))');
$safeLink = $renderer->markdown('[safe](https://example.com)');
$check(!str_contains($unsafeLink, 'href='), 'Markdown allowed an unsafe URL scheme.');
$check(str_contains($safeLink, 'href="https://example.com"'), 'Markdown rejected HTTPS.');

$corePublicKeyPath = APPROOT . '/core/core_update_public_key.pem';
$corePublicKey = is_file($corePublicKeyPath) ? (string) file_get_contents($corePublicKeyPath) : '';
$check($corePublicKey !== '', 'Embedded Core signing public key is missing.');
$check(
    str_contains($corePublicKey, 'BEGIN PUBLIC KEY')
        && !str_contains($corePublicKey, 'PRIVATE KEY'),
    'Core signing key file is not a public-only PEM.'
);

if (extension_loaded('openssl') && $corePublicKey !== '') {
    $check(
        openssl_pkey_get_public($corePublicKey) !== false,
        'Embedded Core signing public key is invalid.'
    );
}

$model = (new ReflectionClass(model::class))->newInstanceWithoutConstructor();
$quote = new ReflectionMethod(model::class, 'quoteIdentifier');
$where = new ReflectionMethod(model::class, 'validateWhereClause');
$check($quote->invoke($model, 'posts') === '`posts`', 'Valid SQL identifier was rejected.');
$check($where->invoke($model, 'id = :id') === 'id = :id', 'Safe WHERE clause was rejected.');

try {
    $quote->invoke($model, 'posts; DROP TABLE posts');
    $failures[] = 'Unsafe SQL identifier was accepted.';
} catch (Throwable $e) {
    // Expected.
}

try {
    $where->invoke($model, 'id = :id OR 1 = 1');
    $failures[] = 'Unsafe WHERE clause was accepted.';
} catch (Throwable $e) {
    // Expected.
}

if (extension_loaded('openssl')) {
    $keyOptions = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA
    ];
    $windowsOpenSslConfig = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';

    if (is_file($windowsOpenSslConfig)) {
        $keyOptions['config'] = $windowsOpenSslConfig;
    }

    $keyPair = openssl_pkey_new($keyOptions);
    $check($keyPair !== false, 'OpenSSL could not create the test signing key.');

    if ($keyPair === false) {
        $keyPair = null;
    }

    if ($keyPair !== null) {
    $details = openssl_pkey_get_details($keyPair);
    $version = '1.1.9';
    $download = 'https://example.com/module.zip';
    $hash = str_repeat('a', 64);
    $message = $version . "\n" . $download . "\n" . $hash;
    openssl_sign($message, $signature, $keyPair, OPENSSL_ALGO_SHA256);

    $admin = (new ReflectionClass(admin::class))->newInstanceWithoutConstructor();
    $verify = new ReflectionMethod(admin::class, 'hasValidUpdateSignature');
    $valid = $verify->invoke(
        $admin,
        $details['key'],
        base64_encode($signature),
        $version,
        $download,
        $hash
    );
    $check($valid === true, 'Valid module signature was rejected.');
    $invalid = $verify->invoke(
        $admin,
        $details['key'],
        base64_encode($signature),
        '1.1.10',
        $download,
        $hash
    );
    $check($invalid === false, 'Tampered module metadata was accepted.');

    $coreMetadata = [
        'version' => '1.1.9',
        'package_url' => 'https://chaos-mvc.org/releases/chaos-mvc-1.1.9.zip',
        'package_sha256' => str_repeat('b', 64),
        'package_size' => 1048576,
        'released_at' => '2026-08-25T00:00:00Z',
        'minimum_updater_version' => '1.1.8',
        'manifest_sha256' => str_repeat('c', 64)
    ];
    openssl_sign(
        core_updater::signatureMessage($coreMetadata),
        $coreSignature,
        $keyPair,
        OPENSSL_ALGO_SHA256
    );
    $coreMetadata['signature'] = base64_encode($coreSignature);
    $coreFetcher = static fn (): string => (string) json_encode($coreMetadata);
    $coreUpdater = new core_updater(
        '1.1.8',
        $details['key'],
        'https://chaos-mvc.org/api/core/update',
        $coreFetcher
    );
    $coreOffer = $coreUpdater->check();
    $check(
        ($coreOffer['outcome'] ?? null) === 'update_available'
            && ($coreOffer['target_version'] ?? null) === '1.1.9',
        'Valid signed Core offer was rejected.'
    );

    $tamperedCoreMetadata = $coreMetadata;
    $tamperedCoreMetadata['package_size']++;
    $tamperedUpdater = new core_updater(
        '1.1.8',
        $details['key'],
        'https://chaos-mvc.org/api/core/update',
        static fn (): string => (string) json_encode($tamperedCoreMetadata)
    );
    $tamperedOffer = $tamperedUpdater->check();
    $check(
        ($tamperedOffer['error_code'] ?? null) === 'core_signature_invalid',
        'Tampered Core metadata was accepted.'
    );

    $moduleShapedMetadata = [
        'version' => '1.1.9',
        'download' => 'https://chaos-mvc.org/module.zip',
        'sha256' => str_repeat('d', 64),
        'signature' => base64_encode($signature)
    ];
    $moduleAuthorityAttempt = new core_updater(
        '1.1.8',
        $details['key'],
        'https://chaos-mvc.org/api/core/update',
        static fn (): string => (string) json_encode($moduleShapedMetadata)
    );
    $moduleAuthorityResult = $moduleAuthorityAttempt->check();
    $check(
        ($moduleAuthorityResult['error_code'] ?? null) === 'core_metadata_field_missing',
        'Module updater metadata was accepted as Core authority.'
    );
    }
}

$lockTestDirectory = sys_get_temp_dir() . '/chaos_core_lock_test_' . bin2hex(random_bytes(8));
$coreLock = new core_update_lock($lockTestDirectory);
$firstLock = $coreLock->acquire('1.1.8', '1.1.9');
$secondLock = $coreLock->acquire('1.1.8', '1.1.9');
$operationId = (string) ($firstLock['state']['operation_id'] ?? '');
$check(($firstLock['outcome'] ?? null) === 'locked', 'Core update lock could not be acquired.');
$check(
    ($secondLock['outcome'] ?? null) === 'update_in_progress',
    'Concurrent Core update lock was accepted.'
);
$check(!$coreLock->release(str_repeat('0', 32)), 'Core update lock allowed release by a non-owner.');
$check($coreLock->updatePhase($operationId, 'verifying'), 'Core update lock phase could not be advanced.');
$check($coreLock->release($operationId), 'Core update lock owner could not release the lock.');
@rmdir($lockTestDirectory);

if (extension_loaded('zip')) {
    $removeTestTree = static function (string $directory) use (&$removeTestTree): void {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path) && !is_link($path)) {
                $removeTestTree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    };
    $buildCorePackage = static function (array $files, array $extraFiles = []): array {
        $version = '1.1.9';
        $manifestFiles = [];

        foreach ($files as $path => $contents) {
            $manifestFiles[] = [
                'path' => $path,
                'sha256' => hash('sha256', $contents),
                'size' => strlen($contents)
            ];
        }

        $manifest = ['schema' => 1, 'version' => $version, 'files' => $manifestFiles];
        $manifestRaw = (string) json_encode($manifest, JSON_UNESCAPED_SLASHES);
        $zipPath = sys_get_temp_dir() . '/chaos_core_package_' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL);
        $root = 'chaos-mvc-' . $version . '/';
        $zip->addFromString($root . 'core-manifest.json', $manifestRaw);

        foreach ($files as $path => $contents) {
            $zip->addFromString($root . $path, $contents);
        }

        foreach ($extraFiles as $path => $contents) {
            $zip->addFromString($root . $path, $contents);
        }

        $zip->close();
        $package = (string) file_get_contents($zipPath);
        @unlink($zipPath);

        return ['bytes' => $package, 'manifest_hash' => hash('sha256', $manifestRaw)];
    };
    $packageFiles = [
        'app/bootstrap.php' => "<?php\n// staged bootstrap\n",
        'app/core/version.php' => "<?php\ndefine('CHAOS_VERSION', '1.1.9');\n",
        'app/core/router.php' => "<?php\nclass router {}\n"
    ];
    $packageFixture = $buildCorePackage($packageFiles);
    $packageOffer = [
        'version' => '1.1.9',
        'package_url' => 'https://chaos-mvc.org/releases/chaos-mvc-1.1.9.zip',
        'package_sha256' => hash('sha256', $packageFixture['bytes']),
        'package_size' => strlen($packageFixture['bytes']),
        'manifest_sha256' => $packageFixture['manifest_hash']
    ];
    $packageTestRoot = sys_get_temp_dir() . '/chaos_core_stage_test_' . bin2hex(random_bytes(8));
    mkdir($packageTestRoot . '/app/core', 0750, true);
    file_put_contents($packageTestRoot . '/app/core/config.php', "<?php\n");
    $packageState = $packageTestRoot . '/.chaos-update';
    $stager = new core_package_stager(
        $packageTestRoot,
        $packageState,
        static fn (): string => $packageFixture['bytes']
    );
    $staged = $stager->stage($packageOffer);
    $check(
        ($staged['outcome'] ?? null) === 'package_staged',
        'Valid Core package was not staged.'
    );
    $stagedRouter = $packageState . '/staging/' . ($staged['operation_id'] ?? '') . '/files/app/core/router.php';
    $check(is_file($stagedRouter), 'Validated Core package file is missing from staging.');

    $digestOffer = $packageOffer;
    $digestOffer['package_sha256'] = str_repeat('0', 64);
    $digestFailure = $stager->stage($digestOffer);
    $check(
        ($digestFailure['error_code'] ?? null) === 'core_package_digest_mismatch',
        'Core package checksum tampering was accepted.'
    );

    $protectedFiles = $packageFiles;
    $protectedFiles['public/index.php'] = "<?php\n";
    $protectedFixture = $buildCorePackage($protectedFiles);
    $protectedOffer = $packageOffer;
    $protectedOffer['package_sha256'] = hash('sha256', $protectedFixture['bytes']);
    $protectedOffer['package_size'] = strlen($protectedFixture['bytes']);
    $protectedOffer['manifest_sha256'] = $protectedFixture['manifest_hash'];
    $protectedStager = new core_package_stager(
        $packageTestRoot,
        $packageState,
        static fn (): string => $protectedFixture['bytes']
    );
    $protectedResult = $protectedStager->stage($protectedOffer);
    $check(
        ($protectedResult['error_code'] ?? null) === 'core_manifest_file_invalid',
        'Core package was allowed to claim /public.'
    );

    $configFiles = $packageFiles;
    $configFiles['app/core/config.php'] = "<?php\n";
    $configFixture = $buildCorePackage($configFiles);
    $configOffer = $packageOffer;
    $configOffer['package_sha256'] = hash('sha256', $configFixture['bytes']);
    $configOffer['package_size'] = strlen($configFixture['bytes']);
    $configOffer['manifest_sha256'] = $configFixture['manifest_hash'];
    $configStager = new core_package_stager(
        $packageTestRoot,
        $packageState,
        static fn (): string => $configFixture['bytes']
    );
    $configResult = $configStager->stage($configOffer);
    $check(
        ($configResult['error_code'] ?? null) === 'core_manifest_file_invalid',
        'Core package was allowed to claim app/core/config.php.'
    );

    $extraFixture = $buildCorePackage($packageFiles, ['app/core/unlisted.php' => "<?php\n"]);
    $extraOffer = $packageOffer;
    $extraOffer['package_sha256'] = hash('sha256', $extraFixture['bytes']);
    $extraOffer['package_size'] = strlen($extraFixture['bytes']);
    $extraOffer['manifest_sha256'] = $extraFixture['manifest_hash'];
    $extraStager = new core_package_stager(
        $packageTestRoot,
        $packageState,
        static fn (): string => $extraFixture['bytes']
    );
    $extraResult = $extraStager->stage($extraOffer);
    $check(
        ($extraResult['error_code'] ?? null) === 'core_archive_unlisted_file',
        'Unlisted Core archive file was accepted.'
    );
    $removeTestTree($packageTestRoot);
}

$schema = (string) file_get_contents(APPROOT . '/install/schema.sql');
$check(str_contains($schema, 'CREATE TABLE `password_resets`'), 'Password reset schema is missing.');
$check(str_contains($schema, 'CREATE TABLE `traffic`'), 'Traffic schema is missing.');
$check(is_file(APPROOT . '/install/migrations/1.1.9.sql'), '1.1.9 migration is missing.');

$postsModel = (string) file_get_contents(APPROOT . '/models/posts_model.php');
$check(
    substr_count($postsModel, 'published = 1') >= 3,
    'Public post or comment queries are missing visibility filters.'
);

foreach (glob(APPROOT . '/controllers/*.php') as $controllerFile) {
    $source = (string) file_get_contents($controllerFile);
    $source = preg_replace('~/\\*.*?\\*/|//[^\\r\\n]*~s', '', $source) ?? $source;
    preg_match_all("/->view\\(\\s*['\"]([^'\"]+)['\"]/", $source, $views);

    foreach ($views[1] as $view) {
        $check(
            is_file(APPROOT . '/views/' . $view . '.php'),
            basename($controllerFile) . " references missing view {$view}."
        );
    }

    preg_match_all("/->model\\(\\s*['\"]([^'\"]+)['\"]/", $source, $models);

    foreach ($models[1] as $referencedModel) {
        $check(
            is_file(APPROOT . '/models/' . $referencedModel . '.php'),
            basename($controllerFile) . " references missing model {$referencedModel}."
        );
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }

    exit(1);
}

echo "All maintenance checks passed.\n";
/* [End AI:GPT-5.6 Sol] */
