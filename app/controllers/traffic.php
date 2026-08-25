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
    /* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
    public function collect(): bool
    {
        if (empty($GLOBALS['CHAOS_TRAFFIC_INTERNAL'])) {
            return false;
        }

        try {
            $model = $this->model('traffic_model');

            return $model->record([
                'host' => substr((string) ($_SERVER['SERVER_NAME'] ?? 'unknown'), 0, 190),
                'uri' => substr((string) ($_SERVER['REQUEST_URI'] ?? '/'), 0, 500),
                'method' => substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 0, 10),
                'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255)
            ]);
        } catch (Throwable $e) {
            error_log('Traffic collection failed: ' . $e->getMessage());
            return false;
        }
    }
    /* [End AI:GPT-5.6 Sol] */

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
