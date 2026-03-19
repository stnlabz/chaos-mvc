<?php

/**
 * Path: /app/controllers/changelog.php
 * * @author [AI: Gemini | 2026-03-18 23:22 UTC]
 * * @approved [Human: P.Mei | 2026-03-18 23:22 UTC];
 */

class changelog extends controller
{
    /**
     * Public-facing changelog index.
     */
    public function index($url = [])
    {
        $model = $this->model('changelog_model');
        $data['updates'] = $model->get_all_updates(); 
        $this->view('public/changelog/index', $data);
    }

    /**
     * Admin management for changelog entries.
     */
    public function admin($params = [])
    {
        $util = new utility(); 
        
        if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] < 7) {
            header("Location: /auth/login");
            exit;
        }

        $model = $this->model('changelog_model');

        // HANDLE POST FIRST (this is missing)
        /* [AI: GPT | 2026-0319 00:05:00 UTC
         * [HUMAN: P.MEI | 2026-03-19 00:07:00 UTC]
        */
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $model->save($_POST);
            $util->redirect_to('/admin/changelog');
            return;
        }

        // THEN handle edit/view logic
        $action = $params[1] ?? null;
        $id     = $params[2] ?? null;
        /*[END AI;GPT] */

        $data['edit_item'] = null;

        if ($action === 'edit' && $id) {
            $data['edit_item'] = $model->get_by_id((int)$id);
        }

        if ($action === 'delete' && $id) {
            $model->remove((int)$id);
            header("Location: /admin/changelog");
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $model->save($_POST);
            header("Location: /admin/changelog");
            exit;
        }

        $data['items'] = $model->get_all_updates();
        $this->view('admin/changelog', $data);
    }
}
