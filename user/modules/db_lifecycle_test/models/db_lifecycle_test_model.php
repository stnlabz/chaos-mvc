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
        if ($target === '1.0.1' && $this->hasMigrationColumn()) return 'current';
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

    public function installSchema(): void
    {
        if ($this->tableExists() || $this->targetVersion() !== '1.0.0') {
            throw new RuntimeException('Initial schema installation is not permitted in this state.');
        }
        // Execute the same packaged schema.sql developers inspect and ship.
        $file = __DIR__ . '/../sql/schema.sql';
        if (!is_file($file) || is_link($file)) {
            throw new RuntimeException('Disposable schema file is missing.');
        }
        $sql = file_get_contents($file);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('Disposable schema is empty.');
        }
        $statements = preg_split('/;\\s*(?:\\r?\\n|$)/', $sql);
        if (!is_array($statements)) {
            throw new RuntimeException('Disposable schema could not be parsed.');
        }
        foreach ($statements as $statement) {
            if (trim($statement) !== '') {
                $this->query(trim($statement));
            }
        }
    }
}
