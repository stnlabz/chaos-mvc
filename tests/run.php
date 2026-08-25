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
    $keyPair = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA
    ]);
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
