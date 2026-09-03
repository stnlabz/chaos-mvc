<?php
// path: /app/lib/installation_registry.php

declare(strict_types=1);

/**
 * Installation Registry Client
 *
 * Owns the persistent installation identifier and the explicitly triggered,
 * non-fatal registration request to chaos-mvc.org.
 */

/* [AI:GPT-5.6 Sol | 2026-09-02 23:59:21 UTC] */
final class installation_registry
{
    private const ENDPOINT = 'https://chaos-mvc.org/registry/register';
    private const MAX_RESPONSE_BYTES = 65536;

    private string $stateFile;

    public function __construct()
    {
        $this->stateFile = USERROOT . '/data/installation.json';
    }

    /**
     * Return local installation identity and registration state.
     */
    public function getState(): array
    {
        $defaults = [
            'installation_id' => null,
            'registration_status' => 'unregistered',
            'registered_at' => null,
            'last_attempt_at' => null,
            'last_error' => null,
        ];

        if (!is_file($this->stateFile)) {
            return $defaults;
        }

        $raw = file_get_contents($this->stateFile);
        $decoded = $raw !== false ? json_decode($raw, true) : null;

        return is_array($decoded)
            ? array_replace($defaults, $decoded)
            : $defaults;
    }

    /**
     * Create the installation identity once and persist pending state.
     */
    public function ensureIdentity(): array
    {
        $state = $this->getState();

        if ($this->isUuid((string) ($state['installation_id'] ?? ''))) {
            return $state;
        }

        $state['installation_id'] = $this->generateUuid();
        $state['registration_status'] = 'pending';
        $state['registered_at'] = null;
        $state['last_error'] = null;

        if (!$this->writeState($state)) {
            throw new RuntimeException('Installation identity could not be persisted.');
        }

        return $state;
    }

    /**
     * Attempt registration. Remote failure is returned as state, not thrown.
     */
    public function register(): array
    {
        try {
            $state = $this->ensureIdentity();
        } catch (Throwable $exception) {
            return array_replace($this->getState(), [
                'registration_status' => 'unregistered',
                'last_error' => 'Local installation identity is unavailable.',
            ]);
        }

        $state['registration_status'] = 'pending';
        $state['last_attempt_at'] = gmdate('c');
        $state['last_error'] = null;
        $this->writeState($state);

        try {
            $payload = json_encode([
                'installation_id' => $state['installation_id'],
                'domain' => $this->domain(),
                'chaos_version' => $this->version(),
                'php_version' => PHP_VERSION,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\nAccept: application/json\r\nConnection: close\r\n",
                    'content' => $payload,
                    'timeout' => 5,
                    'ignore_errors' => true,
                    'follow_location' => 0,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $response = @file_get_contents(
                self::ENDPOINT,
                false,
                $context,
                0,
                self::MAX_RESPONSE_BYTES
            );
            $status = $this->httpStatus($http_response_header ?? []);
            $decoded = is_string($response)
                ? json_decode($response, true)
                : null;

            if (
                $status < 200
                || $status >= 300
                || !is_array($decoded)
                || ($decoded['registered'] ?? false) !== true
                || !hash_equals(
                    (string) $state['installation_id'],
                    (string) ($decoded['installation_id'] ?? '')
                )
            ) {
                throw new RuntimeException('Registry service unavailable.');
            }

            $state['registration_status'] = 'registered';
            $state['registered_at'] = gmdate('c');
            $state['last_error'] = null;
        } catch (Throwable $exception) {
            $state['registration_status'] = 'pending';
            $state['last_error'] = 'Registration service unavailable.';
        }

        $this->writeState($state);
        return $state;
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function isUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        ) === 1;
    }

    private function domain(): string
    {
        $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[0-9a-f:]+\])$/i', $host)
            ? strtolower($host)
            : 'localhost';
    }

    private function version(): string
    {
        if (!defined('CHAOS_VERSION')) {
            require_once APPROOT . '/core/version.php';
        }

        return defined('CHAOS_VERSION') ? (string) CHAOS_VERSION : 'unknown';
    }

    private function httpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', (string) $header, $match)) {
                return (int) $match[1];
            }
        }

        return 0;
    }

    private function writeState(array $state): bool
    {
        $directory = dirname($this->stateFile);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        try {
            $json = json_encode(
                $state,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $exception) {
            return false;
        }

        $temporary = $this->stateFile . '.tmp';

        if (
            file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false
            || !rename($temporary, $this->stateFile)
        ) {
            @unlink($temporary);
            return false;
        }

        @chmod($this->stateFile, 0600);
        return true;
    }
}
/* [End AI:GPT-5.6 Sol] */
