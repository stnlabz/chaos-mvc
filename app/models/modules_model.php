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
