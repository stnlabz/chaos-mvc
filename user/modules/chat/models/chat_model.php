<?php
declare(strict_types=1);

/** Digit 1.0.1 outbound HTTP transport queue. No connection to Digit is made by PHP. */
final class chat_model extends model
{
    private const MAX_REQUEST = 2047;
    private const MAX_REPLY = 511;
    private const TTL = 90;
    private const MAX_RECORDS = 100;

    private function queuePath(): string
    {
        $directory = rtrim(sys_get_temp_dir(), '/') . '/chaos-chat-' .
            substr(hash('sha256', USERROOT), 0, 24);
        if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) {
            throw new RuntimeException('Chat storage is unavailable.');
        }
        if (is_link($directory) || !is_dir($directory) ||
            (fileperms($directory) & 0077) !== 0 ||
            (function_exists('posix_geteuid') &&
             fileowner($directory) !== posix_geteuid())) {
            throw new RuntimeException('Chat storage permissions are unsafe.');
        }
        return $directory . '/messages.json';
    }

    /** All queue state transitions are serialized with an exclusive lock. */
    private function transact(callable $operation): mixed
    {
        $path = $this->queuePath();
        if (is_link($path)) {
            throw new RuntimeException('Chat storage is unavailable.');
        }
        $file = @fopen($path, 'c+');
        if (!is_resource($file)) {
            throw new RuntimeException('Chat storage is unavailable.');
        }
        try {
            if (!flock($file, LOCK_EX)) {
                throw new RuntimeException('Chat storage is busy.');
            }
            $stat = fstat($file);
            if (!is_array($stat) || ($stat['mode'] & 0077) !== 0) {
                @chmod($path, 0600);
                $stat = fstat($file);
                if (!is_array($stat) || ($stat['mode'] & 0077) !== 0) {
                    throw new RuntimeException('Chat storage permissions are unsafe.');
                }
            }
            rewind($file);
            $raw = stream_get_contents($file);
            $records = $raw === '' ? [] : json_decode((string) $raw, true);
            if (!is_array($records)) {
                throw new RuntimeException('Chat storage is corrupted.');
            }
            $now = time();
            foreach ($records as $id => $record) {
                if (!is_array($record) || ($record['created'] ?? 0) < $now - self::TTL) {
                    unset($records[$id]);
                }
            }
            $result = $operation($records);
            $encoded = json_encode($records, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) ||
                !fflush($file)) {
                throw new RuntimeException('Chat storage write failed.');
            }
            return $result;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    /** Resolve the authoritative account label for sessions created before the login update. */
    public function displayNameForUser(int $userId): string
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('Invalid account.');
        }
        $row = $this->fetch(
            'SELECT display_name, username FROM accounts WHERE id = ? LIMIT 1',
            [$userId]
        );
        if (!is_array($row)) {
            throw new RuntimeException('Chat account is unavailable.');
        }
        $name = trim((string) ($row['display_name'] ?? ''));
        return $name !== '' ? $name : (string) $row['username'];
    }

    public function submit(string $message, string $session, string $displayName): string
    {
        $size = strlen($message);
        if ($size < 1 || $size > self::MAX_REQUEST ||
            preg_match('//u', $message) !== 1 ||
            preg_match('/[\x00-\x1f\x7f]/', $message) === 1) {
            throw new InvalidArgumentException('Enter a single-line UTF-8 message (1–2047 bytes).');
        }
        $displayName = trim($displayName);
        if ($displayName === '' || strlen($displayName) > 127 ||
            preg_match('//u', $displayName) !== 1 ||
            preg_match('/[\\x00-\\x1f\\x7f]/', $displayName) === 1) {
            throw new InvalidArgumentException('Invalid account display name.');
        }
        return $this->transact(static function (array &$records) use ($message, $session, $displayName): string {
            if (count($records) >= self::MAX_RECORDS) {
                throw new RuntimeException('Chat queue is busy.');
            }
            $id = bin2hex(random_bytes(16));
            $records[$id] = [
                'session' => $session, 'message' => $message,
                'display_name' => $displayName,
                'state' => 'queued', 'created' => time(),
                'reply' => null, 'result' => null
            ];
            return $id;
        });
    }

    public function pending(): ?array
    {
        return $this->transact(static function (array &$records): ?array {
            foreach ($records as $id => &$record) {
                if ($record['state'] !== 'queued') {
                    continue;
                }
                $record['state'] = 'processing';
                return [
                    'id' => $id,
                    'message' => $record['message'],
                    'display_name' => $record['display_name'] ?? 'digit-web'
                ];
            }
            return null;
        });
    }

    public function receive(string $id, string $status, string $message): bool
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id) !== 1 ||
            !in_array($status, ['ok', 'error'], true) ||
            strlen($message) > self::MAX_REPLY ||
            preg_match('//u', $message) !== 1) {
            throw new InvalidArgumentException('Invalid transport reply.');
        }
        return $this->transact(static function (array &$records) use ($id, $status, $message): bool {
            if (!isset($records[$id]) || $records[$id]['state'] !== 'processing') {
                return false;
            }
            $records[$id]['state'] = 'complete';
            $records[$id]['result'] = $status;
            $records[$id]['reply'] = $message;
            return true;
        });
    }

    public function status(string $id, string $session): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            return null;
        }
        return $this->transact(static function (array &$records) use ($id, $session): ?array {
            $record = $records[$id] ?? null;
            if (!is_array($record) || !hash_equals((string) $record['session'], $session)) {
                return null;
            }
            return [
                'state' => $record['state'],
                'status' => $record['result'],
                'reply' => $record['state'] === 'complete' ? $record['reply'] : null
            ];
        });
    }
}
