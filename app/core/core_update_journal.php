<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_update_journal
{
    private string $operationDirectory;
    private string $journalPath;

    public function __construct(string $stateDirectory, string $operationId)
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $operationId)) {
            throw new InvalidArgumentException('Invalid Core update operation identifier.');
        }

        $this->operationDirectory = rtrim($stateDirectory, '/\\') . '/operations/' . $operationId;
        $this->journalPath = $this->operationDirectory . '/journal.jsonl';

        if (!is_dir($this->operationDirectory) && !@mkdir($this->operationDirectory, 0750, true)) {
            throw new RuntimeException('Core update operation directory could not be created.');
        }
    }

    /**
     * Append one hash-chained journal event.
     *
     * @param array<string, mixed> $data
     */
    public function append(string $event, array $data = []): bool
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $event)) {
            return false;
        }

        $handle = @fopen($this->journalPath, 'c+');

        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return false;
        }

        try {
            $entries = $this->readHandle($handle);

            if (!$this->entriesAreValid($entries)) {
                return false;
            }

            $previous = end($entries);
            $entry = [
                'sequence' => count($entries) + 1,
                'recorded_at' => gmdate('c'),
                'event' => $event,
                'data' => $data,
                'previous_hash' => is_array($previous) ? ($previous['entry_hash'] ?? null) : null
            ];
            $entry['entry_hash'] = hash('sha256', self::canonicalEntry($entry));
            $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES);

            if ($encoded === false || fseek($handle, 0, SEEK_END) !== 0) {
                return false;
            }

            return fwrite($handle, $encoded . PHP_EOL) === strlen($encoded . PHP_EOL)
                && fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function read(): array
    {
        if (!is_file($this->journalPath)) {
            return [];
        }

        $handle = @fopen($this->journalPath, 'r');

        if ($handle === false) {
            return [];
        }

        try {
            return $this->readHandle($handle);
        } finally {
            fclose($handle);
        }
    }

    public function verify(): bool
    {
        return $this->entriesAreValid($this->read());
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function entriesAreValid(array $entries): bool
    {
        $previousHash = null;

        foreach ($entries as $index => $entry) {
            if (
                ($entry['sequence'] ?? null) !== $index + 1 ||
                ($entry['previous_hash'] ?? null) !== $previousHash ||
                !is_string($entry['entry_hash'] ?? null) ||
                !hash_equals($entry['entry_hash'], hash('sha256', self::canonicalEntry($entry)))
            ) {
                return false;
            }

            $previousHash = $entry['entry_hash'];
        }

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readHandle($handle): array
    {
        rewind($handle);
        $entries = [];

        while (($line = fgets($handle)) !== false) {
            if (trim($line) === '') {
                continue;
            }

            $entry = json_decode($line, true);

            if (!is_array($entry)) {
                return [['invalid' => true]];
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function canonicalEntry(array $entry): string
    {
        unset($entry['entry_hash']);

        return (string) json_encode($entry, JSON_UNESCAPED_SLASHES);
    }
}
/* [End AI:GPT-5.6 Sol] */
