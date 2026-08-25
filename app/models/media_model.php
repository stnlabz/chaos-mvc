<?php

/**
 * Media Model
 *
 * Handles database operations for media records.
 *
 * Path: /app/models/media_model.php
 */

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
class media_model extends model
{
    /**
     * Media database table.
     *
     * @var string
     */
    protected $table = 'media';

    /**
     * Retrieve all media records.
     *
     * @return array
     */
    public function get_all(): array
    {
        return $this->fetchAll(
            "SELECT * FROM {$this->table} ORDER BY created_at DESC"
        );
    }

    /**
     * Retrieve a media record by ID.
     *
     * @param int $id Media record ID.
     * @return array|false
     */
    public function get_by_id($id): array|false
    {
        return $this->fetch(
            "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1",
            [(int) $id]
        );
    }

    /**
     * Delete a media record.
     *
     * @param int $id Media record ID.
     * @return bool
     */
    public function delete($id): bool
    {
        $stmt = $this->query(
            "DELETE FROM {$this->table} WHERE id = ?",
            [(int) $id]
        );

        return $stmt->rowCount() > 0;
    }
}
/* [End AI:GPT-5.6 Sol] */