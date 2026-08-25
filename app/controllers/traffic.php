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
     */
    public function collect()
    {
        $model = $this->model('traffic_model');
        $model->record([
            'host'       => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'uri'        => $_SERVER['REQUEST_URI'] ?? '/',
            'method'     => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'referer'    => $_SERVER['HTTP_REFERER'] ?? null
        ]);
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
