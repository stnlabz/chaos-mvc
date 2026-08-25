<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_updater
{
    public const PRODUCTION_ENDPOINT = 'https://chaos-mvc.org/api/core/update';
    private const MAX_METADATA_BYTES = 1048576;

    private string $currentVersion;
    private string $endpoint;
    private string $publicKey;
    private $fetcher;

    public function __construct(
        string $currentVersion,
        string $publicKey,
        string $endpoint = self::PRODUCTION_ENDPOINT,
        ?callable $fetcher = null
    ) {
        $this->currentVersion = $currentVersion;
        $this->publicKey = trim($publicKey);
        $this->endpoint = $endpoint;
        $this->fetcher = $fetcher;
    }

    /**
     * Perform a read-only Core release check.
     *
     * @return array<string, mixed>
     */
    public function check(): array
    {
        if (!$this->isApprovedCoreUrl($this->endpoint)) {
            return $this->failure('authority', 'core_endpoint_invalid', 'The Core update endpoint is invalid.');
        }

        if ($this->publicKey === '') {
            return $this->failure('authority', 'core_signing_key_missing', 'The Core signing key is not configured.');
        }

        try {
            $raw = $this->fetchMetadata();
        } catch (Throwable $exception) {
            return $this->failure('checking', 'core_metadata_fetch_failed', $exception->getMessage());
        }

        if ($raw === '' || strlen($raw) > self::MAX_METADATA_BYTES) {
            return $this->failure('checking', 'core_metadata_size_invalid', 'Core update metadata is empty or too large.');
        }

        $metadata = json_decode($raw, true);

        if (!is_array($metadata)) {
            return $this->failure('checking', 'core_metadata_invalid', 'Core update metadata is not valid JSON.');
        }

        $validationError = $this->validateMetadata($metadata);

        if ($validationError !== null) {
            return $validationError;
        }

        $offeredVersion = $metadata['version'];

        if (!version_compare($offeredVersion, $this->currentVersion, '>')) {
            return [
                'success' => true,
                'outcome' => 'up_to_date',
                'installed_version' => $this->currentVersion,
                'checked_at' => gmdate('c')
            ];
        }

        if (version_compare($this->currentVersion, $metadata['minimum_updater_version'], '<')) {
            return [
                'success' => false,
                'outcome' => 'failed_unchanged',
                'phase' => 'compatibility',
                'error_code' => 'core_updater_too_old',
                'message' => 'This installation cannot install the offered Core release.',
                'installed_version' => $this->currentVersion,
                'target_version' => $offeredVersion,
                'checked_at' => gmdate('c')
            ];
        }

        return [
            'success' => true,
            'outcome' => 'update_available',
            'installed_version' => $this->currentVersion,
            'target_version' => $offeredVersion,
            'released_at' => $metadata['released_at'],
            'package_size' => $metadata['package_size'],
            'offer' => $metadata,
            'checked_at' => gmdate('c')
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>|null
     */
    private function validateMetadata(array $metadata): ?array
    {
        $stringFields = [
            'version',
            'package_url',
            'package_sha256',
            'released_at',
            'minimum_updater_version',
            'manifest_sha256',
            'signature'
        ];

        foreach ($stringFields as $field) {
            if (!isset($metadata[$field]) || !is_string($metadata[$field]) || $metadata[$field] === '') {
                return $this->failure('checking', 'core_metadata_field_missing', "Required Core field is missing: {$field}.");
            }
        }

        if (!isset($metadata['package_size']) || !is_int($metadata['package_size']) || $metadata['package_size'] < 1) {
            return $this->failure('checking', 'core_package_size_invalid', 'The Core package size is invalid.');
        }

        if (
            !preg_match('/^\d+\.\d+\.\d+$/', $metadata['version']) ||
            !preg_match('/^\d+\.\d+\.\d+$/', $metadata['minimum_updater_version']) ||
            !preg_match('/^[a-f0-9]{64}$/', strtolower($metadata['package_sha256'])) ||
            !preg_match('/^[a-f0-9]{64}$/', strtolower($metadata['manifest_sha256'])) ||
            strtotime($metadata['released_at']) === false ||
            !$this->isApprovedCoreUrl($metadata['package_url'])
        ) {
            return $this->failure('checking', 'core_metadata_field_invalid', 'Core update metadata contains an invalid field.');
        }

        if (!$this->verifySignature($metadata)) {
            return $this->failure('authority', 'core_signature_invalid', 'Core update signature verification failed.');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function verifySignature(array $metadata): bool
    {
        if (!extension_loaded('openssl')) {
            return false;
        }

        $decodedSignature = base64_decode($metadata['signature'], true);
        $key = openssl_pkey_get_public($this->publicKey);

        if ($decodedSignature === false || $key === false) {
            return false;
        }

        return openssl_verify(
            self::signatureMessage($metadata),
            $decodedSignature,
            $key,
            OPENSSL_ALGO_SHA256
        ) === 1;
    }

    /**
     * Canonical signed Core offer message.
     *
     * @param array<string, mixed> $metadata
     */
    public static function signatureMessage(array $metadata): string
    {
        return implode("\n", [
            (string) ($metadata['version'] ?? ''),
            (string) ($metadata['package_url'] ?? ''),
            strtolower((string) ($metadata['package_sha256'] ?? '')),
            (string) ($metadata['package_size'] ?? ''),
            (string) ($metadata['released_at'] ?? ''),
            (string) ($metadata['minimum_updater_version'] ?? ''),
            strtolower((string) ($metadata['manifest_sha256'] ?? ''))
        ]);
    }

    private function fetchMetadata(): string
    {
        if (is_callable($this->fetcher)) {
            $result = ($this->fetcher)($this->endpoint, self::MAX_METADATA_BYTES);

            if (!is_string($result)) {
                throw new RuntimeException('Core update service returned no metadata.');
            }

            return $result;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'ignore_errors' => false,
                'follow_location' => 0,
                'header' => "Accept: application/json\r\nConnection: close\r\n"
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ]);
        $result = @file_get_contents($this->endpoint, false, $context, 0, self::MAX_METADATA_BYTES + 1);

        if (!is_string($result)) {
            throw new RuntimeException('The Core update service could not be reached.');
        }

        return $result;
    }

    private function isApprovedCoreUrl(string $url): bool
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
            'message' => $message,
            'installed_version' => $this->currentVersion,
            'checked_at' => gmdate('c')
        ];
    }
}
/* [End AI:GPT-5.6 Sol] */
