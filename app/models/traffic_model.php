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
     * Exclusively for the Admin Controller.
     */
    public function get_log_report($limit = 500): array|false
    {
        /* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
        $limit = max(1, min((int) $limit, 1000));

        return $this->fetchAll(
            "SELECT * FROM traffic ORDER BY created_at DESC LIMIT {$limit}"
        );
        /* [End AI:GPT-5.6 Sol] */
    }
}
