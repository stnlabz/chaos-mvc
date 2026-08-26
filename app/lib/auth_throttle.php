<?php

/**
 * Chaos MVC Authentication Throttle
 *
 * CMSEC-2026-4827-E — Authentication and Recovery Throttling
 *
 * Provides bounded, temporary throttling for login and password-recovery
 * requests without permanent account lockout.
 *
 * Path: /app/lib/auth_throttle.php
 */

class auth_throttle
{
    private const LOGIN_LIMIT = 5;
    private const LOGIN_WINDOW = 900;

    private const RECOVERY_EMAIL_LIMIT = 3;
    private const RECOVERY_IP_LIMIT = 5;
    private const RECOVERY_WINDOW = 1800;

    private const MAX_KEYS_PER_GROUP = 5000;
    private const MAX_EVENTS_PER_KEY = 20;

    private string $file;

    public function __construct()
    {
        $this->file =
            APPROOT
            . '/data/security/auth_throttle.json';
    }

    public function is_login_allowed(
        string $username,
        string $ip
    ): bool {
        $state = $this->load();

        $this->prune($state);

        $userKey =
            'username:'
            . strtolower(
                trim($username)
            );

        $ipKey =
            'ip:'
            . trim($ip);

        return $this->countWithin(
            $state['login'][$userKey] ?? [],
            self::LOGIN_WINDOW
        ) < self::LOGIN_LIMIT
            && $this->countWithin(
                $state['login'][$ipKey] ?? [],
                self::LOGIN_WINDOW
            ) < self::LOGIN_LIMIT;
    }

    public function record_login_failure(
        string $username,
        string $ip
    ): void {
        $userKey =
            'username:'
            . strtolower(
                trim($username)
            );

        $ipKey =
            'ip:'
            . trim($ip);

        $now = time();

        $this->mutate(
            function (array &$state) use (
                $userKey,
                $ipKey,
                $now
            ): void {
                $state['login'][$userKey][] = $now;
                $state['login'][$ipKey][] = $now;

                $state['login'][$userKey] = array_slice(
                    $state['login'][$userKey],
                    -self::MAX_EVENTS_PER_KEY
                );

                $state['login'][$ipKey] = array_slice(
                    $state['login'][$ipKey],
                    -self::MAX_EVENTS_PER_KEY
                );
            }
        );
    }

    public function clear_login_failures(
        string $username,
        string $ip
    ): void {
        $userKey =
            'username:'
            . strtolower(
                trim($username)
            );

        /*
         * A successful account login clears only that account's failures.
         * Retaining the shared IP history prevents a valid account from
         * resetting source-wide credential-stuffing protection.
         */
        $this->mutate(
            static function (array &$state) use ($userKey): void {
                unset($state['login'][$userKey]);
            }
        );
    }

    public function is_recovery_allowed(
        string $email,
        string $ip
    ): bool {
        $state = $this->load();

        $this->prune($state);

        $emailKey =
            'email:'
            . strtolower(
                trim($email)
            );

        $ipKey =
            'ip:'
            . trim($ip);

        return $this->countWithin(
            $state['recovery'][$emailKey] ?? [],
            self::RECOVERY_WINDOW
        ) < self::RECOVERY_EMAIL_LIMIT
            && $this->countWithin(
                $state['recovery'][$ipKey] ?? [],
                self::RECOVERY_WINDOW
            ) < self::RECOVERY_IP_LIMIT;
    }

    public function record_recovery_request(
        string $email,
        string $ip
    ): void {
        $emailKey =
            'email:'
            . strtolower(
                trim($email)
            );

        $ipKey =
            'ip:'
            . trim($ip);

        $now = time();

        $this->mutate(
            function (array &$state) use (
                $emailKey,
                $ipKey,
                $now
            ): void {
                $state['recovery'][$emailKey][] = $now;
                $state['recovery'][$ipKey][] = $now;

                $state['recovery'][$emailKey] = array_slice(
                    $state['recovery'][$emailKey],
                    -self::MAX_EVENTS_PER_KEY
                );

                $state['recovery'][$ipKey] = array_slice(
                    $state['recovery'][$ipKey],
                    -self::MAX_EVENTS_PER_KEY
                );
            }
        );
    }

    private function load(): array
    {
        if (!is_file($this->file)) {
            return $this->emptyState();
        }

        $handle = @fopen($this->file, 'rb');

        if ($handle === false) {
            error_log('Authentication throttle state could not be opened for reading.');
            return $this->emptyState();
        }

        if (!flock($handle, LOCK_SH)) {
            fclose($handle);
            error_log('Authentication throttle state could not be locked for reading.');
            return $this->emptyState();
        }

        $raw = stream_get_contents($handle);

        flock($handle, LOCK_UN);
        fclose($handle);

        return $this->decodeState($raw);
    }

    /**
     * Atomically load, change, and replace throttle state.
     */
    private function mutate(callable $callback): void
    {
        $directory = dirname($this->file);

        if (
            !is_dir($directory)
            && !@mkdir($directory, 0700, true)
            && !is_dir($directory)
        ) {
            error_log('Authentication throttle directory could not be created.');
            return;
        }

        $handle = @fopen($this->file, 'c+b');

        if ($handle === false) {
            error_log('Authentication throttle state could not be opened for writing.');
            return;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            error_log('Authentication throttle state could not be locked for writing.');
            return;
        }

        rewind($handle);
        $state = $this->decodeState(stream_get_contents($handle));

        $this->prune($state);
        $callback($state);
        $this->limitKeys($state);

        $encoded = json_encode(
            $state,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if ($encoded === false) {
            error_log('Authentication throttle state could not be encoded.');
            flock($handle, LOCK_UN);
            fclose($handle);
            return;
        }

        rewind($handle);

        if (
            !ftruncate($handle, 0)
            || fwrite($handle, $encoded . PHP_EOL) === false
            || !fflush($handle)
        ) {
            error_log('Authentication throttle state could not be persisted.');
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        @chmod($this->file, 0600);
    }

    private function decodeState($raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return $this->emptyState();
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            error_log('Authentication throttle state contained invalid JSON.');
            return $this->emptyState();
        }

        $data['login'] =
            is_array($data['login'] ?? null)
                ? $data['login']
                : [];

        $data['recovery'] =
            is_array($data['recovery'] ?? null)
                ? $data['recovery']
                : [];

        return $data;
    }

    private function emptyState(): array
    {
        return [
            'login' => [],
            'recovery' => [],
        ];
    }

    private function prune(array &$state): void
    {
        $now = time();

        foreach (
            [
                'login' => self::LOGIN_WINDOW,
                'recovery' => self::RECOVERY_WINDOW,
            ]
            as $group => $window
        ) {
            foreach (
                $state[$group] ?? []
                as $key => $timestamps
            ) {
                if (!is_array($timestamps)) {
                    unset(
                        $state[$group][$key]
                    );

                    continue;
                }

                $timestamps = array_values(
                    array_filter(
                        $timestamps,
                        static function ($timestamp) use (
                            $now,
                            $window
                        ): bool {
                            return is_int($timestamp)
                                && ($now - $timestamp) < $window;
                        }
                    )
                );

                if ($timestamps === []) {
                    unset(
                        $state[$group][$key]
                    );
                } else {
                    $state[$group][$key] =
                        $timestamps;
                }
            }
        }
    }

    private function countWithin(
        array $timestamps,
        int $window
    ): int {
        $now = time();

        return count(
            array_filter(
                $timestamps,
                static function ($timestamp) use (
                    $now,
                    $window
                ): bool {
                    return is_int($timestamp)
                        && ($now - $timestamp) < $window;
                }
            )
        );
    }

    /**
     * Retain only the most recently active keys in each state group.
     */
    private function limitKeys(array &$state): void
    {
        foreach (['login', 'recovery'] as $group) {
            if (count($state[$group]) <= self::MAX_KEYS_PER_GROUP) {
                continue;
            }

            uasort(
                $state[$group],
                static function (array $left, array $right): int {
                    return (max($right) ?: 0) <=> (max($left) ?: 0);
                }
            );

            $state[$group] = array_slice(
                $state[$group],
                0,
                self::MAX_KEYS_PER_GROUP,
                true
            );
        }
    }
}
