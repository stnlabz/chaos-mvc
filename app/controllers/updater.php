<?php

/**
 * Chaos MVC Core Updater Controller
 *
 * Provides the administrator interface and controlled execution path
 * for Chaos MVC Core updates.
 *
 * Path: /app/controllers/updater.php
 */

/* [AI:GPT-5.6 Sol | 2026-08-25 20:15:00 UTC] */
class updater extends controller
{
    public static $is_core = true;

    /**
     * Display Core updater administration.
     *
     * @param array $params Route parameters.
     * @return void
     */
    public function admin($params = []): void
    {
        $this->require_admin(7);

        require_once APPROOT . '/lib/updater.php';

        $engine = new updater_engine();

        $data = [
            'current_version' => $engine->getCurrentVersion(),
            'update' => $engine->checkForUpdate(),
            'status' => $engine->getStatus()
        ];

        $this->view('admin/updater', $data);
    }

    /**
     * Check the authoritative Chaos MVC release manifest.
     *
     * @return never
     */
    public function check(): never
    {
        $this->require_admin(7);

        header('Content-Type: application/json');

        require_once APPROOT . '/lib/updater.php';

        $engine = new updater_engine();

        echo json_encode(
            $engine->checkForUpdate(),
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }

    /**
     * Execute a Core update.
     *
     * @return never
     */
    public function run(): never
    {
        $this->require_admin(7);

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);

            echo json_encode([
                'success' => false,
                'message' => 'Core updates require POST.'
            ]);

            exit;
        }

        $this->require_csrf();

        /*
         * Release the PHP session lock so the administrator interface
         * can continue polling /updater/status while the update runs.
         */
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        require_once APPROOT . '/lib/updater.php';

        $engine = new updater_engine();

        try {
            $result = $engine->run();

            echo json_encode(
                $result,
                JSON_UNESCAPED_SLASHES
            );
        } catch (Throwable $e) {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    /**
     * Return current updater status.
     *
     * @return never
     */
    public function status(): never
    {
        $this->require_admin(7);

        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        /*
         * Do not hold the session while status is being read.
         */
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        require_once APPROOT . '/lib/updater.php';

        $engine = new updater_engine();

        echo json_encode(
            $engine->getStatus(),
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }
}
/* [End AI:GPT-5.6 Sol] */