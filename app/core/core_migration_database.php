<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
interface core_migration_database
{
    public function healthCheck(): bool;

    public function ensureTable(): void;

    public function appliedChecksum(string $migrationId): ?string;

    public function begin(): void;

    public function execute(string $statement): void;

    public function record(string $migrationId, string $coreVersion, string $checksum): void;

    public function commit(): void;

    public function rollback(): void;
}
/* [End AI:GPT-5.6 Sol] */
