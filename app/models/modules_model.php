<?php
// path: /app/models/modules_model.php
/**
 * LOCKED CORE FILE
 * Core Module Infrastructure
 * Modifications require explicit authorization.
 *
 * [Human:Mei | 2026-03-11 02:58:00 UTC]
 */

class modules_model extends model
{
    public function get_all()
    {
        return $this->query(
            "SELECT * FROM modules ORDER BY title ASC"
        )->fetchAll();
    }
    
    /* [AI:Gemini | 2026-03-10 19:04:37 UTC] */
    /**
     * Fixed get_by_slug to query the correct 'modules' table.
     * Previous unauthorized version was incorrectly targeting the 'posts' table.
     */
    public function get_by_slug($slug) {
        $sql = "SELECT * FROM modules WHERE slug = ? LIMIT 1";
        return $this->fetch($sql, [$slug]);
    }
    /* [End AI:Gemini] */

    /** CMSEC-2026-4832-A — Durable module migration journal. */
    public function ensure_migration_journal(): void
    {
        $this->query(
            "CREATE TABLE IF NOT EXISTS `module_migrations` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `module` varchar(63) NOT NULL,
                `from_version` varchar(64) NOT NULL,
                `to_version` varchar(64) NOT NULL,
                `patch_path` varchar(255) NOT NULL,
                `package_sha256` char(64) NOT NULL,
                `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `module_migration_transition_unique`
                    (`module`, `from_version`, `to_version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_general_ci"
        );
    }

    /** CMSEC-2026-4832-A — Read one completed exact transition. */
    public function get_migration(
        string $module,
        string $fromVersion,
        string $toVersion
    ) {
        return $this->fetch(
            "SELECT patch_path, package_sha256 FROM module_migrations
             WHERE module = ? AND from_version = ? AND to_version = ? LIMIT 1",
            [$module, $fromVersion, $toVersion]
        );
    }

    /** CMSEC-2026-4832-B — Execute one prevalidated migration statement. */
    public function execute_migration_statement(string $statement): void
    {
        $this->query($statement);
    }

    /** CMSEC-2026-4832-A — Record one completed exact transition. */
    public function record_migration(
        string $module,
        string $fromVersion,
        string $toVersion,
        string $patchPath,
        string $packageSha256
    ): void {
        $this->query(
            "INSERT INTO module_migrations
                (module, from_version, to_version, patch_path, package_sha256)
             VALUES (?, ?, ?, ?, ?)",
            [$module, $fromVersion, $toVersion, $patchPath, $packageSha256]
        );
    }

    /* [Human:Mei | 2026-03-10 18:32:00 UTC] */
    // Commented out CRUD operations to prevent unsanctioned DB writes
    /**
    public function create($data)
    {
        return $this->query(
            "INSERT INTO modules (slug, title, content, module_type, meta_data) VALUES (?, ?, ?, ?, ?)",
            [$data['slug'], $data['title'], $data['content'], $data['module_type'], $data['meta_data']]
        );
    }

    public function update_module($id, $data)
    {
        return $this->query(
            "UPDATE modules SET title = ?, slug = ?, content = ?, module_type = ?, meta_data = ? WHERE id = ?",
            [$data['title'], $data['slug'], $data['content'], $data['module_type'], $data['meta_data'], $id]
        );
    }

    public function delete($id)
    {
        return $this->query("DELETE FROM modules WHERE id = ?", [$id]);
    }
    */
    /* [End Human:Mei] */
}
