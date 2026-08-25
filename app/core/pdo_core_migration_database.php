<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class pdo_core_migration_database implements core_migration_database
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function healthCheck(): bool
    {
        return $this->database->query('SELECT 1')->fetchColumn() !== false;
    }

    public function ensureTable(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS `core_migrations` ('
            . '`migration_id` varchar(100) NOT NULL,'
            . '`core_version` varchar(30) NOT NULL,'
            . '`checksum` char(64) NOT NULL,'
            . '`applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . 'PRIMARY KEY (`migration_id`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function appliedChecksum(string $migrationId): ?string
    {
        $statement = $this->database->prepare(
            'SELECT `checksum` FROM `core_migrations` WHERE `migration_id` = ? LIMIT 1'
        );
        $statement->execute([$migrationId]);
        $value = $statement->fetchColumn();

        return is_string($value) ? $value : null;
    }

    public function begin(): void
    {
        if (!$this->database->inTransaction()) {
            $this->database->beginTransaction();
        }
    }

    public function execute(string $statement): void
    {
        $this->database->exec($statement);
    }

    public function record(string $migrationId, string $coreVersion, string $checksum): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO `core_migrations` (`migration_id`, `core_version`, `checksum`) VALUES (?, ?, ?)'
        );
        $statement->execute([$migrationId, $coreVersion, $checksum]);
    }

    public function commit(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->commit();
        }
    }

    public function rollback(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }
}
/* [End AI:GPT-5.6 Sol] */
