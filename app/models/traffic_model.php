<?php

/**
 * Path: /app/models/traffic_model.php
 * * @author [AI: Gemini | 2026-03-19 15:58 UTC]
 * * @approved [Human: P.Mei | 2026-03-19 15:58 UTC];
 */

class traffic_model extends model
{
    /**
     * The background "Sentinel" style hook calls this.
     */
    public function record($data): bool
    {
        return $this->insert('traffic', [
            'host'       => $data['host'],
            'uri'        => $data['uri'],
            'method'     => $data['method'],
            'ip'         => $data['ip'],
            'user_agent' => $data['user_agent'],
            'referer'    => $data['referer'],
            'user_id'    => $_SESSION['user_id'] ?? null
        ]);
    }

    /**
     * Apply the traffic retention window and hard row ceiling.
     *
     * CMSEC-2026-4830-G — Bounded traffic records and retention
     */
    public function prune(): void
    {
        $this->query(
            'DELETE FROM traffic WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)'
        );
        $this->query(
            'DELETE FROM traffic WHERE id NOT IN ('
            . 'SELECT id FROM ('
            . 'SELECT id FROM traffic ORDER BY id DESC LIMIT 100000'
            . ') AS retained'
            . ')'
        );
    }

    /**
     * Exclusively for the Admin Controller.
     */
    public function get_log_report($limit = 500): array|false
    {
        return $this->fetchAll("SELECT * FROM traffic ORDER BY created_at DESC LIMIT ?", [$limit]);
    }
}
