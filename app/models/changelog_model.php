<?php

/**
 * Path: /app/models/changelog_model.php
 * * @author [AI: Gemini | 2026-03-18 23:22 UTC]
 * * @approved [Human: P.Mei | 2026-03-18 23:22 UTC];
 */

class changelog_model extends model
{
    public function get_all_updates()
    {
        return $this->fetchAll("SELECT * FROM changelog ORDER BY date_released DESC, id DESC");
    }

    public function get_by_id(int $id)
    {
        // fetch() returns the single associative array the view needs
        return $this->fetch("SELECT * FROM changelog WHERE id = ?", [$id]);
    }

    public function remove(int $id)
    {
        return $this->query("DELETE FROM changelog WHERE id = ?", [$id]);
    }

    public function save(array $post)
    {
        $payload = [
            'version'       => $post['version'],
            'category'      => $post['category'] ?? 'maintenance',
            'description'   => $post['description'],
            'date_released' => $post['date_released'] ?? date('Y-m-d')
        ];

        if (!empty($post['id'])) {
            return $this->query(
                "UPDATE changelog SET version = ?, category = ?, description = ?, date_released = ? WHERE id = ?",
                [$payload['version'], $payload['category'], $payload['description'], $payload['date_released'], (int)$post['id']]
            );
        }

        return $this->insert('changelog', $payload);
    }
}
