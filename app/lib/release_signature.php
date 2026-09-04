<?php
declare(strict_types=1);

/** Shared publisher verification for module and theme release statements. */
final class release_signature
{
    public static function verify(array $trusted, string $keyId, string $encodedSignature, string $statement): void
    {
        $algorithm = strtolower((string) ($trusted['algorithm'] ?? $trusted['type'] ?? ''));
        if (isset($trusted['algorithm'], $trusted['type'])
            && strtolower((string) $trusted['algorithm']) !== strtolower((string) $trusted['type'])) {
            throw new RuntimeException('Conflicting release signing algorithms.');
        }
        if (!in_array($algorithm, ['rsa-sha256', 'openpgp', 'pgp'], true)
            || $keyId === '' || !hash_equals((string) ($trusted['key_id'] ?? ''), $keyId)) {
            throw new RuntimeException('Release signing trust is missing or invalid.');
        }
        $publicKey = trim((string) ($trusted['public_key'] ?? ''));
        if (!str_starts_with($publicKey, '-----BEGIN ')) {
            $publicKey = base64_decode($publicKey, true);
        }
        $signature = base64_decode($encodedSignature, true);
        if (!is_string($publicKey) || $publicKey === '' || $signature === false || $signature === '') {
            throw new RuntimeException('Release public key or signature encoding is invalid.');
        }
        if ($algorithm !== 'rsa-sha256') {
            self::verifyPgp($publicKey, $signature, $statement);
            return;
        }
        $key = openssl_pkey_get_public($publicKey);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (!is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA
            || (int) ($details['bits'] ?? 0) < 3072) {
            throw new RuntimeException('Release signing key must be RSA-3072 or stronger.');
        }
        if (openssl_verify($statement, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Release signature verification failed.');
        }
    }

    private static function verifyPgp(string $publicKey, string $signature, string $statement): void
    {
        if (!extension_loaded('gnupg') || version_compare((string) phpversion('gnupg'), '1.5.0', '<')) {
            throw new RuntimeException('PGP updates require PHP GnuPG extension 1.5 or newer; no checksum-only fallback is permitted.');
        }
        $home = sys_get_temp_dir() . '/chaos-pgp-' . bin2hex(random_bytes(16));
        if (!mkdir($home, 0700)) {
            throw new RuntimeException('Cannot create isolated PGP verification keyring.');
        }
        try {
            $gpg = new gnupg(['home_dir' => $home]);
            $gpg->seterrormode(GNUPG_ERROR_EXCEPTION);
            $import = $gpg->import($publicKey);
            if (!is_array($import) || ($import['imported'] ?? 0) !== 1
                || ($import['secret_imported'] ?? 0) !== 0 || ($import['secret_read'] ?? 0) !== 0) {
                throw new RuntimeException('PGP trust must contain exactly one public publisher key.');
            }
            $keys = $gpg->keyinfo((string) ($import['fingerprint'] ?? ''));
            if (!is_array($keys) || count($keys) !== 1) {
                throw new RuntimeException('PGP publisher key is invalid.');
            }
            $publisher = $keys[0];
            if (!empty($publisher['revoked']) || !empty($publisher['expired']) || !empty($publisher['disabled']) || !empty($publisher['invalid'])) {
                throw new RuntimeException('PGP publisher key is revoked, expired or disabled.');
            }
            $fingerprints = [];
            foreach ($publisher['subkeys'] ?? [] as $subkey) {
                if (empty($subkey['revoked']) && empty($subkey['expired']) && empty($subkey['disabled']) && empty($subkey['invalid'])) {
                    $fingerprints[] = $subkey['fingerprint'] ?? '';
                }
            }
            $results = $gpg->verify($statement, $signature);
            if (!is_array($results) || count($results) !== 1
                || ($results[0]['status'] ?? -1) !== 0
                || !isset($results[0]['summary']) || ($results[0]['summary'] & ~3) !== 0
                || !in_array($results[0]['fingerprint'] ?? null, $fingerprints, true)) {
                throw new RuntimeException('PGP release signature verification failed.');
            }
        } finally {
            unset($gpg);
            self::removeKeyring($home);
        }
    }

    /** Only called for the randomly named private keyring created above. */
    private static function removeKeyring(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $directory . '/' . $name;
            if (is_dir($path) && !is_link($path)) {
                self::removeKeyring($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
