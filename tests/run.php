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
require_once APPROOT . '/core/core_backup_manager.php';
require_once APPROOT . '/core/core_file_installer.php';
require_once APPROOT . '/core/core_install_verifier.php';
require_once APPROOT . '/core/core_migration_database.php';
require_once APPROOT . '/core/core_migration_runner.php';
require_once APPROOT . '/core/core_maintenance.php';
require_once APPROOT . '/core/pdo_core_migration_database.php';
require_once APPROOT . '/core/core_update_lock.php';
require_once APPROOT . '/core/core_update_engine.php';
require_once APPROOT . '/core/core_update_journal.php';
require_once APPROOT . '/core/core_package_stager.php';
require_once APPROOT . '/core/core_recovery_service.php';
require_once APPROOT . '/core/core_updater.php';
require_once APPROOT . '/controllers/admin.php';
require_once APPROOT . '/api/core_release_builder.php';

$failures = [];

$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

final class core_test_migration_database implements core_migration_database
{
    public array $applied = [];
    public array $statements = [];
    public ?string $failOn = null;
    public bool $transaction = false;
    public bool $rolledBack = false;

    public function healthCheck(): bool
    {
        return true;
    }

    public function ensureTable(): void
    {
    }

    public function appliedChecksum(string $migrationId): ?string
    {
        return $this->applied[$migrationId] ?? null;
    }

    public function begin(): void
    {
        $this->transaction = true;
        $this->rolledBack = false;
    }

    public function execute(string $statement): void
    {
        if ($this->failOn !== null && str_contains($statement, $this->failOn)) {
            throw new RuntimeException('Injected migration failure.');
        }

        $this->statements[] = $statement;
    }

    public function record(string $migrationId, string $coreVersion, string $checksum): void
    {
        $this->applied[$migrationId] = $checksum;
    }

    public function commit(): void
    {
        $this->transaction = false;
    }

    public function rollback(): void
    {
        $this->transaction = false;
        $this->rolledBack = true;
    }
}

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
    $upToDateUpdater = new core_updater(
        '1.1.9',
        $details['key'],
        core_updater::PRODUCTION_ENDPOINT,
        $coreFetcher
    );
    $check(
        ($upToDateUpdater->check()['outcome'] ?? null) === 'up_to_date',
        'A successful post-update check did not report Core up to date.'
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

$backupTestRoot = sys_get_temp_dir() . '/chaos_core_backup_test_' . bin2hex(random_bytes(8));
$backupStateRoot = $backupTestRoot . '/.chaos-update';
mkdir($backupTestRoot . '/app/core', 0750, true);
mkdir($backupTestRoot . '/app/controllers', 0750, true);
file_put_contents($backupTestRoot . '/app/bootstrap.php', "<?php\n// installed bootstrap\n");
file_put_contents($backupTestRoot . '/app/core/version.php', "<?php\ndefine('CHAOS_VERSION', '1.1.8');\n");
file_put_contents($backupTestRoot . '/app/core/config.php', "<?php\ndefine('DB_NAME', 'test');\n");
file_put_contents($backupTestRoot . '/app/controllers/home.php', "<?php\nclass home {}\n");
$backupOwnedFiles = [
    'app/bootstrap.php',
    'app/core/version.php',
    'app/controllers/home.php'
];
$installedManifest = [
    'schema' => 1,
    'version' => '1.1.8',
    'files' => array_map(
        static function (string $path) use ($backupTestRoot): array {
            $contents = (string) file_get_contents($backupTestRoot . '/' . $path);

            return [
                'path' => $path,
                'sha256' => hash('sha256', $contents),
                'size' => strlen($contents)
            ];
        },
        $backupOwnedFiles
    )
];
$backupManager = new core_backup_manager($backupTestRoot, $backupStateRoot);
$backupOperation = bin2hex(random_bytes(16));
$backupJournal = new core_update_journal($backupStateRoot, $backupOperation);
$backupResult = $backupManager->create(
    $backupOperation,
    '1.1.8',
    $installedManifest,
    $backupJournal
);
$check(
    ($backupResult['outcome'] ?? null) === 'backup_verified',
    'Current Core backup was not created and verified.'
);
$check($backupJournal->verify(), 'Core operation journal hash chain is invalid.');
$check($backupManager->verifyTemporary($backupOperation), 'Temporary Core backup verification failed.');
$backupHomePath = $backupStateRoot . '/operations/' . $backupOperation . '/backup/files/app/controllers/home.php';
file_put_contents($backupHomePath, "tampered\n");
$check(!$backupManager->verifyTemporary($backupOperation), 'Tampered Core backup was accepted.');
$invalidRestore = $backupManager->restoreTemporary($backupOperation, $installedManifest, $backupJournal);
$check(
    ($invalidRestore['outcome'] ?? null) === 'failed_recovery_required',
    'An invalid recovery backup did not require manual recovery.'
);
copy($backupTestRoot . '/app/controllers/home.php', $backupHomePath);
$check($backupManager->verifyTemporary($backupOperation), 'Repaired Core backup did not verify.');
$check(
    $backupManager->promote($backupOperation, $backupJournal),
    'Verified Core backup could not be promoted for rollback.'
);
$check($backupManager->verifyRollback(), 'Promoted Core rollback failed verification.');

$secondBackupOperation = bin2hex(random_bytes(16));
$secondBackupJournal = new core_update_journal($backupStateRoot, $secondBackupOperation);
$secondBackup = $backupManager->create(
    $secondBackupOperation,
    '1.1.8',
    $installedManifest,
    $secondBackupJournal
);
$check(($secondBackup['success'] ?? false) === true, 'Second Core backup could not be created.');
$check(
    $backupManager->promote($secondBackupOperation, $secondBackupJournal),
    'New Core rollback could not replace the previous rollback.'
);
$check(
    count(glob($backupStateRoot . '/rollback*') ?: []) === 1,
    'More than one completed Core rollback was retained.'
);

$protectedOperation = bin2hex(random_bytes(16));
$protectedJournal = new core_update_journal($backupStateRoot, $protectedOperation);
$protectedInstalledManifest = $installedManifest;
$protectedInstalledManifest['files'][] = [
    'path' => 'public/index.php',
    'sha256' => str_repeat('0', 64),
    'size' => 0
];
$protectedBackup = $backupManager->create(
    $protectedOperation,
    '1.1.8',
    $protectedInstalledManifest,
    $protectedJournal
);
$check(
    ($protectedBackup['error_code'] ?? null) === 'core_installed_manifest_invalid',
    'Installed Core manifest was allowed to claim /public.'
);

$journalTamperOperation = bin2hex(random_bytes(16));
$journalTamper = new core_update_journal($backupStateRoot, $journalTamperOperation);
$check($journalTamper->append('test_started'), 'Core journal test event could not be written.');
$journalTamperPath = $backupStateRoot . '/operations/' . $journalTamperOperation . '/journal.jsonl';
$journalTamperRaw = (string) file_get_contents($journalTamperPath);
file_put_contents($journalTamperPath, str_replace('test_started', 'test_changed', $journalTamperRaw));
$check(!$journalTamper->verify(), 'Tampered Core operation journal was accepted.');
$check(!$journalTamper->append('test_finished'), 'A tampered Core journal accepted another event.');

$newCoreContents = [
    'app/bootstrap.php' => "<?php\n// new bootstrap\n",
    'app/core/version.php' => "<?php\ndefine('CHAOS_VERSION', '1.1.9');\n",
    'app/core/new.php' => "<?php\nclass new_core_file {}\n"
];
$newCoreManifest = [
    'schema' => 1,
    'version' => '1.1.9',
    'files' => array_map(
        static fn (string $path, string $contents): array => [
            'path' => $path,
            'sha256' => hash('sha256', $contents),
            'size' => strlen($contents)
        ],
        array_keys($newCoreContents),
        array_values($newCoreContents)
    )
];
$createStagedCore = static function (
    string $root,
    array $contents,
    array $manifest
): string {
    $stagedRoot = $root . '/staged-' . bin2hex(random_bytes(6));

    foreach ($contents as $path => $fileContents) {
        $destination = $stagedRoot . '/' . $path;
        @mkdir(dirname($destination), 0750, true);
        file_put_contents($destination, $fileContents);
    }

    file_put_contents(
        $stagedRoot . '/core-manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    return $stagedRoot;
};

$installLock = new core_update_lock($backupStateRoot);
$installLockResult = $installLock->acquire('1.1.8', '1.1.9');
$installOperation = (string) ($installLockResult['state']['operation_id'] ?? '');
$installJournal = new core_update_journal($backupStateRoot, $installOperation);
$installBackup = $backupManager->create(
    $installOperation,
    '1.1.8',
    $installedManifest,
    $installJournal
);
$check(($installBackup['success'] ?? false) === true, 'Installer fixture backup failed.');
$stagedCoreRoot = $createStagedCore($backupTestRoot, $newCoreContents, $newCoreManifest);
$fileInstaller = new core_file_installer($backupTestRoot, $installLock, $backupManager);
$installResult = $fileInstaller->installFiles(
    $installOperation,
    $stagedCoreRoot,
    $installedManifest,
    $newCoreManifest,
    $installJournal
);
$check(($installResult['outcome'] ?? null) === 'files_installed', 'Core filesystem installation failed.');
$check(
    file_get_contents($backupTestRoot . '/app/bootstrap.php') === $newCoreContents['app/bootstrap.php'],
    'New Core bootstrap was not installed.'
);
$check(
    str_contains((string) file_get_contents($backupTestRoot . '/app/core/version.php'), '1.1.8'),
    'Core version was committed before explicit finalization.'
);
$check(!is_file($backupTestRoot . '/app/controllers/home.php'), 'Obsolete Core file was not removed.');
$check(is_file($backupTestRoot . '/app/core/new.php'), 'New manifest-owned Core file was not installed.');
$installVerificationDatabase = new core_test_migration_database();
$installVerifier = new core_install_verifier(
    $backupTestRoot,
    $backupManager,
    $installVerificationDatabase,
    null,
    static fn (): bool => true
);
$preCommitVerification = $installVerifier->preCommit(
    $installOperation,
    $stagedCoreRoot,
    $installedManifest,
    $newCoreManifest,
    $installJournal
);
$check(
    ($preCommitVerification['outcome'] ?? null) === 'precommit_verified',
    'Core pre-commit verification failed.'
);
$commitResult = $fileInstaller->commitVersion(
    $installOperation,
    $stagedCoreRoot,
    $newCoreManifest,
    $installJournal
);
$check(($commitResult['outcome'] ?? null) === 'version_committed', 'Core version commit failed.');
$check(
    str_contains((string) file_get_contents($backupTestRoot . '/app/core/version.php'), '1.1.9'),
    'Committed Core version is incorrect.'
);
$postCommitVerification = $installVerifier->postCommit($newCoreManifest, $installJournal);
$check(
    ($postCommitVerification['outcome'] ?? null) === 'install_verified',
    'Core post-commit verification failed.'
);
file_put_contents($backupTestRoot . '/app/core/new.php', "tampered\n");
$tamperedPostCommit = $installVerifier->postCommit($newCoreManifest, $installJournal);
$check(
    ($tamperedPostCommit['error_code'] ?? null) === 'core_verify_file_failed',
    'Post-commit verification accepted a tampered Core file.'
);
$manualRestore = $backupManager->restoreTemporary($installOperation, $newCoreManifest, $installJournal);
$check(($manualRestore['outcome'] ?? null) === 'failed_restored', 'Verified Core restoration failed.');
$check(is_file($backupTestRoot . '/app/controllers/home.php'), 'Restoration did not recover an obsolete Core file.');
$check(!is_file($backupTestRoot . '/app/core/new.php'), 'Restoration did not remove a newly installed Core file.');
$check($installLock->release($installOperation), 'Installer fixture lock could not be released.');

$failureLockResult = $installLock->acquire('1.1.8', '1.1.9');
$failureOperation = (string) ($failureLockResult['state']['operation_id'] ?? '');
$failureJournal = new core_update_journal($backupStateRoot, $failureOperation);
$failureBackup = $backupManager->create(
    $failureOperation,
    '1.1.8',
    $installedManifest,
    $failureJournal
);
$check(($failureBackup['success'] ?? false) === true, 'Failure fixture backup failed.');
$failureInjected = false;
$failingInstaller = new core_file_installer(
    $backupTestRoot,
    $installLock,
    $backupManager,
    static function (string $phase, string $path) use (&$failureInjected): bool {
        if (!$failureInjected && $phase === 'after_replace' && $path === 'app/bootstrap.php') {
            $failureInjected = true;
            return true;
        }

        return false;
    }
);
$failureResult = $failingInstaller->installFiles(
    $failureOperation,
    $stagedCoreRoot,
    $installedManifest,
    $newCoreManifest,
    $failureJournal
);
$check(($failureResult['outcome'] ?? null) === 'failed_restored', 'Mid-install failure did not restore Core.');
$check(
    file_get_contents($backupTestRoot . '/app/bootstrap.php') === "<?php\n// installed bootstrap\n",
    'Mid-install restoration did not recover the original bootstrap.'
);
$check(is_file($backupTestRoot . '/app/controllers/home.php'), 'Mid-install restoration lost an old Core file.');
$check(!is_file($backupTestRoot . '/app/core/new.php'), 'Mid-install restoration retained a new Core file.');
$check(
    file_get_contents($backupTestRoot . '/app/core/config.php') === "<?php\ndefine('DB_NAME', 'test');\n",
    'Core installation or restoration changed protected configuration.'
);
$check($installLock->release($failureOperation), 'Failure fixture lock could not be released.');

$migrationSql = "-- migration fixture\nCREATE TABLE example (value varchar(20));\n"
    . "INSERT INTO example (value) VALUES ('semi;colon');\n";
$migrationPath = $stagedCoreRoot . '/app/install/migrations/1.1.9.sql';
@mkdir(dirname($migrationPath), 0750, true);
file_put_contents($migrationPath, $migrationSql);
$migrationManifest = $newCoreManifest;
$migrationManifest['migrations'] = [[
    'id' => '1.1.9',
    'path' => 'app/install/migrations/1.1.9.sql',
    'sha256' => hash('sha256', $migrationSql)
]];
$migrationDatabase = new core_test_migration_database();
$migrationRunner = new core_migration_runner($migrationDatabase);
$migrationOperation = bin2hex(random_bytes(16));
$migrationJournal = new core_update_journal($backupStateRoot, $migrationOperation);
$migrationResult = $migrationRunner->run($migrationManifest, $stagedCoreRoot, $migrationJournal);
$check(($migrationResult['outcome'] ?? null) === 'migrations_complete', 'Core migration did not complete.');
$check(($migrationResult['applied'] ?? null) === 1, 'Core migration was not recorded exactly once.');
$check(count($migrationDatabase->statements) === 2, 'Core SQL statements were split incorrectly.');
$check(
    str_contains($migrationDatabase->statements[1] ?? '', "'semi;colon'"),
    'Semicolon inside a SQL string was split as a statement boundary.'
);
$repeatMigration = $migrationRunner->run($migrationManifest, $stagedCoreRoot, $migrationJournal);
$check(($repeatMigration['applied'] ?? null) === 0, 'Applied Core migration ran more than once.');
$migrationDatabase->applied['1.1.9'] = str_repeat('f', 64);
$conflictMigration = $migrationRunner->run($migrationManifest, $stagedCoreRoot, $migrationJournal);
$check(
    ($conflictMigration['error_code'] ?? null) === 'core_migration_history_conflict',
    'Changed checksum for an applied Core migration was accepted.'
);

$failureMigrationSql = "CREATE TABLE before_failure (id int);\nFAIL STATEMENT;\n";
$failureMigrationPath = $stagedCoreRoot . '/app/install/migrations/failure.sql';
file_put_contents($failureMigrationPath, $failureMigrationSql);
$failureMigrationManifest = $newCoreManifest;
$failureMigrationManifest['migrations'] = [[
    'id' => 'failure',
    'path' => 'app/install/migrations/failure.sql',
    'sha256' => hash('sha256', $failureMigrationSql)
]];
$failureMigrationDatabase = new core_test_migration_database();
$failureMigrationDatabase->failOn = 'FAIL';
$failureMigrationRunner = new core_migration_runner($failureMigrationDatabase);
$failureMigrationOperation = bin2hex(random_bytes(16));
$failureMigrationJournal = new core_update_journal($backupStateRoot, $failureMigrationOperation);
$failedMigration = $failureMigrationRunner->run(
    $failureMigrationManifest,
    $stagedCoreRoot,
    $failureMigrationJournal
);
$check(
    ($failedMigration['error_code'] ?? null) === 'core_migration_execution_failed',
    'Core migration execution failure was not reported.'
);
$check($failureMigrationDatabase->rolledBack, 'Failed Core migration did not request transaction rollback.');

$maintenance = new core_maintenance($backupStateRoot);
$maintenanceOperation = bin2hex(random_bytes(16));
$check($maintenance->activate($maintenanceOperation, 'installing'), 'Core maintenance could not be activated.');
$check($maintenance->shouldBlock([]), 'Core maintenance did not block an anonymous request.');
$check(
    $maintenance->shouldBlock(['user_level' => 8]),
    'Core maintenance did not block a non-administrator request.'
);
$check(
    !$maintenance->shouldBlock(['user_level' => 9]),
    'Core maintenance blocked a level-9 administrator.'
);
$check(
    !$maintenance->deactivate(str_repeat('0', 32)),
    'Core maintenance was deactivated by a non-owner.'
);
$check($maintenance->update($maintenanceOperation, 'verifying'), 'Core maintenance phase could not be updated.');
$check($maintenance->deactivate($maintenanceOperation), 'Core maintenance owner could not deactivate it.');
$check(!$maintenance->shouldBlock([]), 'Inactive Core maintenance still blocked requests.');

file_put_contents(
    $backupTestRoot . '/.chaos-core-manifest.json',
    json_encode($installedManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);
$moduleStatePath = $backupTestRoot . '/app/data/modules/example/config.json';
@mkdir(dirname($moduleStatePath), 0750, true);
file_put_contents($moduleStatePath, "{\"version\":\"7.4.2\"}\n");
$moduleStateBeforeUpdate = hash_file('sha256', $moduleStatePath);
$engineStageId = bin2hex(random_bytes(16));
$engineStageRoot = $backupStateRoot . '/staging/' . $engineStageId;

foreach ($newCoreContents as $path => $contents) {
    $destination = $engineStageRoot . '/files/' . $path;
    @mkdir(dirname($destination), 0750, true);
    file_put_contents($destination, $contents);
}

file_put_contents(
    $engineStageRoot . '/files/core-manifest.json',
    json_encode($newCoreManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);
file_put_contents(
    $engineStageRoot . '/state.json',
    json_encode([
        'operation_id' => $engineStageId,
        'phase' => 'staged',
        'target_version' => '1.1.9'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);
$engineDatabase = new core_test_migration_database();
$updateEngine = new core_update_engine(
    $backupTestRoot,
    $backupStateRoot,
    $engineDatabase,
    null,
    static fn (): bool => true
);
$engineResult = $updateEngine->install($engineStageId);
$check(($engineResult['outcome'] ?? null) === 'updated', 'End-to-end Core update did not complete.');
$installedAfterEngine = json_decode(
    (string) file_get_contents($backupTestRoot . '/.chaos-core-manifest.json'),
    true
);
$check(($installedAfterEngine['version'] ?? null) === '1.1.9', 'Installed Core manifest was not committed.');
$check((new core_update_lock($backupStateRoot))->read() === null, 'Core lock remained after successful update.');
$check(!(new core_maintenance($backupStateRoot))->shouldBlock([]), 'Maintenance remained after successful update.');
$check((new core_backup_manager($backupTestRoot, $backupStateRoot))->verifyRollback(), 'Successful update retained no rollback.');
$check(
    hash_equals((string) $moduleStateBeforeUpdate, (string) hash_file('sha256', $moduleStatePath)),
    'Core update changed independently versioned module state.'
);

$failureEngineContents = [
    'app/bootstrap.php' => "<?php\n// 1.1.10 bootstrap\n",
    'app/core/version.php' => "<?php\ndefine('CHAOS_VERSION', '1.1.10');\n",
    'app/core/new.php' => "<?php\nclass new_core_file {}\n"
];
$failureEngineManifest = [
    'schema' => 1,
    'version' => '1.1.10',
    'files' => array_map(
        static fn (string $path, string $contents): array => [
            'path' => $path,
            'sha256' => hash('sha256', $contents),
            'size' => strlen($contents)
        ],
        array_keys($failureEngineContents),
        array_values($failureEngineContents)
    )
];
$failureEngineStageId = bin2hex(random_bytes(16));
$failureEngineStageRoot = $backupStateRoot . '/staging/' . $failureEngineStageId;

foreach ($failureEngineContents as $path => $contents) {
    $destination = $failureEngineStageRoot . '/files/' . $path;
    @mkdir(dirname($destination), 0750, true);
    file_put_contents($destination, $contents);
}

file_put_contents(
    $failureEngineStageRoot . '/files/core-manifest.json',
    json_encode($failureEngineManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);
file_put_contents(
    $failureEngineStageRoot . '/state.json',
    json_encode([
        'operation_id' => $failureEngineStageId,
        'phase' => 'staged',
        'target_version' => '1.1.10'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);
$engineFaultUsed = false;
$failingEngine = new core_update_engine(
    $backupTestRoot,
    $backupStateRoot,
    new core_test_migration_database(),
    null,
    static fn (): bool => true,
    null,
    static function (string $phase, string $path) use (&$engineFaultUsed): bool {
        if (!$engineFaultUsed && $phase === 'after_replace' && $path === 'app/bootstrap.php') {
            $engineFaultUsed = true;
            return true;
        }

        return false;
    }
);
$failedEngineResult = $failingEngine->install($failureEngineStageId);
$check(
    ($failedEngineResult['outcome'] ?? null) === 'failed_restored',
    'End-to-end mid-install failure did not restore the previous Core.'
);
$check(
    str_contains((string) file_get_contents($backupTestRoot . '/app/core/version.php'), '1.1.9'),
    'Failed end-to-end update changed the installed Core version.'
);
$check((new core_update_lock($backupStateRoot))->read() === null, 'Core lock remained after verified restoration.');
$check(!(new core_maintenance($backupStateRoot))->shouldBlock([]), 'Maintenance remained after verified restoration.');

$recoveryService = new core_recovery_service($backupTestRoot, $backupStateRoot);
$retainedRecovery = $recoveryService->recover();
$check(
    ($retainedRecovery['outcome'] ?? null) === 'failed_restored',
    'Administrator recovery did not restore the retained previous Core.'
);
$check(
    str_contains((string) file_get_contents($backupTestRoot . '/app/core/version.php'), '1.1.8'),
    'Administrator recovery restored the wrong Core version.'
);
$recoveredManifest = json_decode(
    (string) file_get_contents($backupTestRoot . '/.chaos-core-manifest.json'),
    true
);
$check(($recoveredManifest['version'] ?? null) === '1.1.8', 'Recovery did not restore Core ownership state.');

file_put_contents($backupTestRoot . '/app/bootstrap.php', "corrupted\n");
$cliProcess = proc_open(
    [
        PHP_BINARY,
        APPROOT . '/tools/core-recover.php',
        '--root=' . $backupTestRoot,
        '--confirm'
    ],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $cliPipes
);
$cliExit = -1;

if (is_resource($cliProcess)) {
    stream_get_contents($cliPipes[1]);
    stream_get_contents($cliPipes[2]);
    fclose($cliPipes[1]);
    fclose($cliPipes[2]);
    $cliExit = proc_close($cliProcess);
}

$check($cliExit === 0, 'Standalone Core recovery CLI failed.');
$check(
    file_get_contents($backupTestRoot . '/app/bootstrap.php') === "<?php\n// installed bootstrap\n",
    'Standalone Core recovery CLI did not restore a corrupted Core file.'
);

$releaseFixture = sys_get_temp_dir() . '/chaos-core-release-' . bin2hex(random_bytes(8));
$releaseOutput = $releaseFixture . '-output';
mkdir($releaseFixture . '/app/core', 0750, true);
mkdir($releaseFixture . '/app/install/migrations', 0750, true);
mkdir($releaseFixture . '/public', 0750, true);
mkdir($releaseFixture . '/app/tools', 0750, true);
file_put_contents($releaseFixture . '/app/bootstrap.php', "<?php\n");
file_put_contents($releaseFixture . '/app/core/version.php', "<?php define('CHAOS_VERSION', '1.1.9');\n");
file_put_contents($releaseFixture . '/app/core/config.php', "<?php // protected\n");
file_put_contents($releaseFixture . '/app/install/migrations/1.1.9.sql', "SELECT 1;\n");
file_put_contents($releaseFixture . '/public/index.php', "<?php // site-owned\n");
file_put_contents($releaseFixture . '/app/tools/core-recover.php', "<?php\n");

$releaseKeyOptions = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
$releaseOpenSslConfig = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';

if (is_file($releaseOpenSslConfig)) {
    $releaseKeyOptions['config'] = $releaseOpenSslConfig;
}

$releaseKey = openssl_pkey_new($releaseKeyOptions);
$releasePrivatePem = '';
$releasePublicPem = '';

if ($releaseKey !== false
    && openssl_pkey_export($releaseKey, $releasePrivatePem, null, $releaseKeyOptions)) {
    $releaseKeyDetails = openssl_pkey_get_details($releaseKey);
    $releasePublicPem = is_array($releaseKeyDetails) ? (string) ($releaseKeyDetails['key'] ?? '') : '';
}

mkdir($releaseOutput, 0750, true);
$releasePrivatePath = $releaseOutput . '/core.private.pem';
file_put_contents($releasePrivatePath, $releasePrivatePem);
$releasePackagePath = $releaseOutput . '/chaos-mvc-1.1.9.zip';
$releaseMetadataPath = $releaseOutput . '/1.1.9.json';
$releaseBuilder = new core_release_builder($releaseFixture);
$releaseResult = $releaseBuilder->build(
    '1.1.9',
    $releasePackagePath,
    $releaseMetadataPath,
    'https://chaos-mvc.org/releases/chaos-mvc-1.1.9.zip',
    $releasePrivatePath,
    '1.1.8'
);
$releasePaths = array_column($releaseResult['manifest']['files'], 'path');
$check(is_file($releasePackagePath) && is_file($releaseMetadataPath), 'Core release builder produced no artifacts.');
$check(!in_array('app/core/config.php', $releasePaths, true), 'Core release builder included protected config.');
$check(!in_array('public/index.php', $releasePaths, true), 'Core release builder included site-owned public files.');
$check(
    ($releaseResult['manifest']['migrations'][0]['id'] ?? null) === '1.1.9',
    'Core release builder did not declare the target migration.'
);
$releaseMetadataRaw = (string) file_get_contents($releaseMetadataPath);
$releaseUpdater = new core_updater(
    '1.1.8',
    $releasePublicPem,
    core_updater::PRODUCTION_ENDPOINT,
    static fn (): string => $releaseMetadataRaw
);
$check(
    ($releaseUpdater->check()['outcome'] ?? null) === 'update_available',
    'Core release builder metadata did not pass updater verification.'
);

$versionMismatchRejected = false;

try {
    $releaseBuilder->manifest('1.2.0');
} catch (RuntimeException) {
    $versionMismatchRejected = true;
}

$check($versionMismatchRejected, 'Core release builder accepted a mismatched version.php.');

$removeBackupTestTree = static function (string $directory) use (&$removeBackupTestTree): void {
    if (!is_dir($directory)) {
        return;
    }

    foreach (scandir($directory) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . '/' . $item;

        if (is_dir($path) && !is_link($path)) {
            $removeBackupTestTree($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($directory);
};
$removeBackupTestTree($backupTestRoot);
$removeBackupTestTree($releaseFixture);
$removeBackupTestTree($releaseOutput);

$schema = (string) file_get_contents(APPROOT . '/install/schema.sql');
$check(str_contains($schema, 'CREATE TABLE `password_resets`'), 'Password reset schema is missing.');
$check(str_contains($schema, 'CREATE TABLE `traffic`'), 'Traffic schema is missing.');
$check(str_contains($schema, 'CREATE TABLE `core_migrations`'), 'Core migration registry schema is missing.');
$check(is_file(APPROOT . '/install/migrations/1.1.9.sql'), '1.1.9 migration is missing.');
$generatedOwnership = (new core_release_builder(dirname(__DIR__)))->manifest('1.1.8');
$installedOwnership = json_decode(
    (string) file_get_contents(dirname(__DIR__) . '/.chaos-core-manifest.json'),
    true
);
$check(
    $installedOwnership === $generatedOwnership,
    'Installed Core ownership manifest is missing or stale.'
);

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
