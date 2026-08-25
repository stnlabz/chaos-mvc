<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_migration_runner
{
    private core_migration_database $database;

    public function __construct(core_migration_database $database)
    {
        $this->database = $database;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function run(array $manifest, string $stagedFilesRoot, core_update_journal $journal): array
    {
        $version = $manifest['version'] ?? null;
        $migrations = $manifest['migrations'] ?? [];

        if (!is_string($version) || !preg_match('/^\d+\.\d+\.\d+$/', $version) || !is_array($migrations)) {
            return $this->failure('core_migration_manifest_invalid', 'Core migration metadata is invalid.');
        }

        try {
            $this->database->ensureTable();
        } catch (Throwable $exception) {
            return $this->failure('core_migration_table_failed', 'The Core migration registry is unavailable.');
        }

        $applied = 0;

        foreach ($migrations as $migration) {
            if (!is_array($migration)) {
                return $this->failure('core_migration_manifest_invalid', 'A Core migration entry is invalid.');
            }

            $id = $migration['id'] ?? null;
            $path = $migration['path'] ?? null;
            $checksum = strtolower((string) ($migration['sha256'] ?? ''));

            if (
                !is_string($id) || !preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id) ||
                !is_string($path) || !preg_match('~^app/install/migrations/[a-z0-9._-]+\.sql$~', $path) ||
                !preg_match('/^[a-f0-9]{64}$/', $checksum)
            ) {
                return $this->failure('core_migration_manifest_invalid', 'A Core migration entry is invalid.');
            }

            $migrationPath = rtrim($stagedFilesRoot, '/\\') . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);

            if (!is_file($migrationPath) || !hash_equals($checksum, (string) hash_file('sha256', $migrationPath))) {
                return $this->failure('core_migration_digest_invalid', 'A Core migration file is missing or invalid.');
            }

            try {
                $existingChecksum = $this->database->appliedChecksum($id);

                if ($existingChecksum !== null) {
                    if (!hash_equals($existingChecksum, $checksum)) {
                        return $this->failure('core_migration_history_conflict', 'An applied Core migration has a different checksum.');
                    }

                    if (!$journal->append('migration_skipped', ['id' => $id])) {
                        return $this->failure('core_journal_write_failed', 'The Core migration journal could not be written.');
                    }
                    continue;
                }

                $sql = (string) file_get_contents($migrationPath);
                $statements = self::splitStatements($sql);

                if ($statements === []) {
                    return $this->failure('core_migration_empty', 'A Core migration contains no SQL statements.');
                }

                if (!$journal->append('migration_started', ['id' => $id])) {
                    return $this->failure('core_journal_write_failed', 'The Core migration journal could not be written.');
                }

                $this->database->begin();

                foreach ($statements as $statement) {
                    $this->database->execute($statement);
                }

                $this->database->record($id, $version, $checksum);
                $this->database->commit();
                $applied++;

                if (!$journal->append('migration_applied', ['id' => $id, 'sha256' => $checksum])) {
                    return $this->failure('core_journal_write_failed', 'The Core migration journal could not be written.');
                }
            } catch (Throwable $exception) {
                try {
                    $this->database->rollback();
                } catch (Throwable $rollbackException) {
                    // The failure result below requires filesystem recovery and administrator inspection.
                }
                $journal->append('migration_failed', ['id' => $id]);

                return $this->failure('core_migration_execution_failed', 'A Core database migration failed.');
            }
        }

        return [
            'success' => true,
            'outcome' => 'migrations_complete',
            'phase' => 'migrating',
            'applied' => $applied
        ];
    }

    /**
     * Split SQL on semicolons outside quoted strings and comments.
     *
     * @return array<int, string>
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);
        $lineComment = false;
        $blockComment = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($lineComment) {
                if ($character === "\n") {
                    $lineComment = false;
                    $buffer .= $character;
                }
                continue;
            }

            if ($blockComment) {
                if ($character === '*' && $next === '/') {
                    $blockComment = false;
                    $index++;
                }
                continue;
            }

            if ($quote === null && $character === '/' && $next === '*') {
                $blockComment = true;
                $index++;
                continue;
            }

            if ($quote === null && $character === '#') {
                $lineComment = true;
                continue;
            }

            if ($quote === null && $character === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
                $lineComment = true;
                $index++;
                continue;
            }

            if ($quote !== null) {
                $buffer .= $character;

                if ($character === '\\' && $next !== '') {
                    $buffer .= $next;
                    $index++;
                    continue;
                }

                if ($character === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $index++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
                $buffer .= $character;
                continue;
            }

            if ($character === ';') {
                $statement = trim($buffer);

                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $character;
        }

        $statement = trim($buffer);

        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(string $code, string $message): array
    {
        return [
            'success' => false,
            'outcome' => 'migration_failed',
            'phase' => 'migrating',
            'error_code' => $code,
            'message' => $message
        ];
    }
}
/* [End AI:GPT-5.6 Sol] */
