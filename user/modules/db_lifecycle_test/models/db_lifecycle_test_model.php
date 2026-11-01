<?php
declare(strict_types=1);

/** Isolated disposable database fixture, no dependencies beyond the MVC model. */
final class db_lifecycle_test_model extends model
{
    private const TABLE = 'db_lifecycle_test_records';

    public function targetVersion(): string
    {
        $raw = file_get_contents(__DIR__ . '/../module.json');
        $metadata = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($metadata) ? (string) ($metadata['schema_version'] ?? '') : '';
    }

    public function tableExists(): bool
    {
        return (bool) $this->fetch(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table_name LIMIT 1',
            ['schema' => DB_NAME, 'table_name' => self::TABLE]
        );
    }

    public function hasMigrationColumn(): bool
    {
        return $this->tableExists() && (bool) $this->fetch(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table_name AND column_name = :column LIMIT 1',
            ['schema' => DB_NAME, 'table_name' => self::TABLE, 'column' => 'qualified_at']
        );
    }

    public function databaseState(): string
    {
        if (!$this->tableExists()) return 'missing';
        $target = $this->targetVersion();
        if ($target === '1.0.0' && !$this->hasMigrationColumn()) return 'current';
        if ($target === '1.0.1') {
            return $this->hasMigrationColumn() ? 'current' : 'update';
        }
        return 'invalid';
    }

    public function recordCount(): int
    {
        if (!$this->tableExists()) return 0;
        $row = $this->fetch('SELECT COUNT(*) AS total FROM `' . self::TABLE . '`');
        return is_array($row) ? (int) ($row['total'] ?? 0) : 0;
    }

    public function referenceFound(): bool
    {
        if (!$this->tableExists()) return false;
        return (bool) $this->fetch(
            'SELECT 1 FROM `' . self::TABLE . '` WHERE id = 1 AND note = :note LIMIT 1',
            ['note' => 'ChAoS MVC database lifecycle qualification']
        );
    }

    /** Install a fresh schema at the module's declared target version. */
    public function installSchema(): void
    {
        if ($this->databaseState() !== 'missing') {
            throw new RuntimeException('Schema is already installed.');
        }
        if (!in_array($this->targetVersion(), ['1.0.0', '1.0.1'], true)) {
            throw new RuntimeException('Unsupported database schema target.');
        }
        $this->executeSqlFile(__DIR__ . '/../sql/schema.sql');
    }

    /** Update only this module's existing 1.0.0 table to schema 1.0.1. */
    public function updateSchema(): void
    {
        if ($this->databaseState() !== 'update' || $this->targetVersion() !== '1.0.1') {
            throw new RuntimeException('No supported database schema update is pending.');
        }
        $this->executeSqlFile(__DIR__ . '/../sql/patches/1.0.0-to-1.0.1.sql');
        if ($this->databaseState() !== 'current') {
            throw new RuntimeException('Database schema update did not complete.');
        }
    }

    /** Data-only removal; leaves the schema intact. */
    public function deleteData(): void
    {
        if ($this->databaseState() !== 'current') {
            throw new RuntimeException('Database schema must be current.');
        }
        $this->query('DELETE FROM `' . self::TABLE . '`');
    }

    /** Restore the module's canonical reference row. */
    public function resetData(): void
    {
        if ($this->databaseState() !== 'current') {
            throw new RuntimeException('Database schema must be current.');
        }
        $this->query('DELETE FROM `' . self::TABLE . '`');
        $this->query(
            'INSERT INTO `' . self::TABLE . '` (id, note) VALUES (1, :note)',
            ['note' => 'ChAoS MVC database lifecycle qualification']
        );
    }

    /** Execute only module-owned, packaged SQL files; never accept SQL from requests. */
    private function executeSqlFile(string $file): void
    {
        if (!is_file($file) || is_link($file)) {
            throw new RuntimeException('Module SQL file is missing.');
        }
        $sql = file_get_contents($file);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('Module SQL file is empty.');
        }
        $statements = preg_split('/;\\s*(?:\\r?\\n|$)/', $sql);
        if (!is_array($statements)) {
            throw new RuntimeException('Module SQL file could not be parsed.');
        }
        foreach ($statements as $statement) {
            if (trim($statement) !== '') {
                $this->query(trim($statement));
            }
        }
    }
}
