<?php
declare(strict_types=1);

// path: /user/modules/example/models/example_model.php

/**
 * Example Model
 *
 * Demonstrates module-owned schema lifecycle, data lifecycle, and CRUD.
 */

final class example_model extends model
{
    private const TABLES = [
        'example_schema',
        'example_records',
    ];

    private const STATE_TABLE = 'example_schema';
    private const RECORD_TABLE = 'example_records';
    private const REFERENCE_FILE = __DIR__ . '/../sql/references.sql';

    public function getModuleInformation(): array
    {
        return [
            'name' => 'Example',
            'slug' => 'example',
            'version' => '2.0.0',
            'schema_version' => $this->targetVersion(),
            'tables' => self::TABLES,
        ];
    }

    /*
     * -----------------------------------------------------------------
     * Module / Data Lifecycle
     * -----------------------------------------------------------------
     */

    public function databaseState(): string
    {
        foreach (self::TABLES as $table) {
            if (!$this->tableExists($table)) {
                return 'missing';
            }
        }

        $current = $this->schemaVersion();
        $target = $this->targetVersion();

        if ($current === null || $target === '') {
            return 'invalid';
        }

        if ($current === $target) {
            return 'current';
        }

        return $this->patchFile($current, $target) !== null
            ? 'update'
            : 'invalid';
    }

    public function installSchema(): void
    {
        $this->executeSqlFile(__DIR__ . '/../sql/schema.sql');

        if (is_file(self::REFERENCE_FILE)) {
            $this->restoreReferenceData();
        }
    }

    public function updateSchema(): void
    {
        $current = $this->schemaVersion();
        $target = $this->targetVersion();

        $patch = $current === null
            ? null
            : $this->patchFile($current, $target);

        if ($patch === null) {
            throw new RuntimeException(
                'No valid Example schema migration path exists.'
            );
        }

        $this->executeSqlFile($patch);

        $this->query(
            'UPDATE `' . self::STATE_TABLE . '` '
            . 'SET `schema_version` = :version '
            . 'WHERE `id` = 1',
            ['version' => $target]
        );
    }

    /**
     * Delete module-owned data while preserving installed schema.
     */
    public function deleteData(): void
    {
        if (!$this->tableExists(self::RECORD_TABLE)) {
            throw new RuntimeException(
                'Example data table is not installed.'
            );
        }

        $this->query('DELETE FROM `' . self::RECORD_TABLE . '`');
    }

    /**
     * Restore the canonical records supplied by the Module.
     */
    public function restoreReferenceData(): void
    {
        if (!$this->tableExists(self::RECORD_TABLE)) {
            throw new RuntimeException(
                'Example data table is not installed.'
            );
        }

        $this->executeSqlFile(self::REFERENCE_FILE);
    }

    /**
     * Explicit Data Reset: remove mutable data, then restore references.
     */
    public function resetData(): void
    {
        $this->deleteData();
        $this->restoreReferenceData();
    }

    /*
     * -----------------------------------------------------------------
     * CRUD
     * -----------------------------------------------------------------
     */

    public function getRecords(): array
    {
        return $this->fetchAll(
            'SELECT `id`, `title`, `body`, `is_active`, '
            . '`created_at`, `updated_at` '
            . 'FROM `' . self::RECORD_TABLE . '` '
            . 'ORDER BY `id` DESC'
        ) ?: [];
    }

    public function getRecord(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        $row = $this->fetch(
            'SELECT `id`, `title`, `body`, `is_active`, '
            . '`created_at`, `updated_at` '
            . 'FROM `' . self::RECORD_TABLE . '` '
            . 'WHERE `id` = :id LIMIT 1',
            ['id' => $id]
        );

        return is_array($row) ? $row : null;
    }

    public function createRecord(array $input): int
    {
        return (int) $this->insert(
            self::RECORD_TABLE,
            $this->validatedRecord($input)
        );
    }

    public function updateRecord(array $input): void
    {
        $id = (int) ($input['id'] ?? 0);

        if ($id < 1 || $this->getRecord($id) === null) {
            throw new InvalidArgumentException(
                'A valid Example record is required.'
            );
        }

        $this->update(
            self::RECORD_TABLE,
            $this->validatedRecord($input),
            'id = :id',
            ['id' => $id]
        );
    }

    public function deleteRecord(int $id): void
    {
        if ($id < 1 || $this->getRecord($id) === null) {
            throw new InvalidArgumentException(
                'A valid Example record is required.'
            );
        }

        $this->query(
            'DELETE FROM `' . self::RECORD_TABLE . '` '
            . 'WHERE `id` = :id',
            ['id' => $id]
        );
    }

    private function validatedRecord(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));

        if ($title === '' || strlen($title) > 150) {
            throw new InvalidArgumentException(
                'Title is required and must be 150 characters or fewer.'
            );
        }

        if ($body === '' || strlen($body) > 2000) {
            throw new InvalidArgumentException(
                'Body is required and must be 2,000 characters or fewer.'
            );
        }

        return [
            'title' => $title,
            'body' => $body,
            'is_active' => isset($input['is_active']) ? 1 : 0,
        ];
    }

    /*
     * -----------------------------------------------------------------
     * Lifecycle Helpers
     * -----------------------------------------------------------------
     */

    private function schemaVersion(): ?string
    {
        if (!$this->tableExists(self::STATE_TABLE)) {
            return null;
        }

        $row = $this->fetch(
            'SELECT `schema_version` '
            . 'FROM `' . self::STATE_TABLE . '` '
            . 'WHERE `id` = 1 LIMIT 1'
        );

        $version = is_array($row)
            ? trim((string) ($row['schema_version'] ?? ''))
            : '';

        return $this->validVersion($version)
            ? $version
            : null;
    }

    private function targetVersion(): string
    {
        $raw = file_get_contents(__DIR__ . '/../module.json');
        $metadata = is_string($raw)
            ? json_decode($raw, true)
            : null;

        $version = is_array($metadata)
            ? trim((string) ($metadata['schema_version'] ?? ''))
            : '';

        return $this->validVersion($version)
            ? $version
            : '';
    }

    private function validVersion(string $version): bool
    {
        return preg_match(
            '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/',
            $version
        ) === 1;
    }

    private function patchFile(
        string $current,
        string $target
    ): ?string {
        $file = __DIR__
            . '/../sql/patches/'
            . $current
            . '-to-'
            . $target
            . '.sql';

        return is_file($file) && !is_link($file)
            ? $file
            : null;
    }

    private function executeSqlFile(string $file): void
    {
        if (!is_file($file) || is_link($file)) {
            throw new RuntimeException(
                'Example SQL file could not be read.'
            );
        }

        $sql = file_get_contents($file);

        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException(
                'Example SQL file could not be read.'
            );
        }

        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);

        if (!is_array($statements)) {
            throw new RuntimeException(
                'Example SQL file could not be parsed.'
            );
        }

        foreach ($statements as $statement) {
            $statement = trim($statement);

            if ($statement !== '') {
                $this->query($statement);
            }
        }
    }

    private function tableExists(string $table): bool
    {
        if (!in_array($table, self::TABLES, true)) {
            return false;
        }

        return (bool) $this->fetch(
            'SELECT 1 FROM information_schema.tables '
            . 'WHERE table_schema = :schema '
            . 'AND table_name = :table_name '
            . 'LIMIT 1',
            [
                'schema' => DB_NAME,
                'table_name' => $table,
            ]
        );
    }
}