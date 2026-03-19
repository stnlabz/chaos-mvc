<?php

/**
 * Path: /app/models/bugs_model.php
 * Data mapping for the Chaos MVC bug tracking system.
 * * @author [AI: Gemini | 2026-03018 1920 UTC]
 * * @approved [Human: P.Mei | 2026-03-18 1936 UTC];
 */

class bugs_model extends model
{
    /**
     * Retrieve all bugs ordered by newest first.
     *
     * @return array|false
     */
    public function all(): array|false
    {
        return $this->fetchAll("select * from bugs order by created_at desc");
    }

    /**
     * Generate a unique 10-character hexadecimal hash.
     *
     * @return string
     */
    public function generate_hash(): string
    {
        return substr(bin2hex(random_bytes(5)), 0, 10);
    }

    /**
     * Locate a bug record by its 6-digit prefix.
     *
     * @param string $hash 6-character short hash.
     * @return array|false
     */
    public function get_by_short_hash(string $hash): array|false
    {
        $sql = "select * from bugs where bug_hash like ? limit 1";
        return $this->fetch($sql, [$hash . '%']);
    }

    /**
     * Get the comment thread with associated account roles.
     *
     * @param int $id The internal bug ID.
     * @return array|false
     */
    public function get_thread(int $id): array|false
    {
        $sql = "select c.*, a.role 
                from bug_comments c
                join accounts a on c.user_id = a.id
                where c.bug_id = ? 
                order by c.created_at asc";
        return $this->fetchAll($sql, [$id]);
    }
}
