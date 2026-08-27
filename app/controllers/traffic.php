<?php

/**
 * Path: /app/controllers/traffic.php
 * * @author [AI: Gemini | 2026-03-19 15:58 UTC]
 * * @approved [Human: P.Mei | 2026-03-19 15:58 UTC];
 */

class traffic extends controller
{
    public static $is_core = true;
    /**
     * This is the background execution method.
     * It is NOT accessible via URL as a public view.
     *
     * CMSEC-2026-4830-F — Internal traffic collector
     * CMSEC-2026-4830-G — Bounded traffic records and retention
     */
    public function collect()
    {
        $model = $this->model('traffic_model');
        $model->record([
            'host'       => substr((string) ($_SERVER['HTTP_HOST'] ?? 'unknown'), 0, 255),
            'uri'        => substr((string) ($_SERVER['REQUEST_URI'] ?? '/'), 0, 2048),
            'method'     => substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 0, 16),
            'ip'         => substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1024),
            'referer'    => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 2048)
        ]);

        if (random_int(1, 1000) === 1) {
            $model->prune();
        }
    }

    /**
     * Admin-only access to view the data.
     * Path: /traffic/admin
     */
    public function admin($params = [])
    {
        if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] < 7) {
            header("Location: /auth/login");
            exit;
        }

        $model = $this->model('traffic_model');
        $data['title'] = "Traffic Engine Logs";
        $data['logs'] = $model->get_log_report();

        $this->view('admin/traffic', $data);
    }
}
