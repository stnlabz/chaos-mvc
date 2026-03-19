<?php

/**
 * Path: /app/controllers/bugs.php
 * Handle bug reporting, viewing, and status management.
 * * @author [AI: Gemini | 2026-03018 1920 UTC]
 * * @approved [Human: P.Mei | 2026-03-18 1936 UTC];
 */

class bugs extends controller
{
    /**
     * Display a list of all bug reports.
     *
     * @return void
     */
    public function index(): void
    {
        $data['list'] = $this->model('bugs_model')->all();
        $this->view('public/bugs/index', $data);
    }

    /**
     * Display a specific bug report via its short hash.
     *
     * @param string|array|null $hash 6-digit hash from the URL.
     * @return void
     */
    public function show($hash = null): void
    {
        if (is_array($hash)) {
            $hash = $hash[0];
        }

        if (!$hash) {
            header("location: /bugs");
            exit;
        }

        $bug = $this->model('bugs_model')->get_by_short_hash((string)$hash);
        
        if (!$bug) {
            header("location: /bugs");
            exit;
        }

        $data['bug'] = $bug;
        $data['thread'] = $this->model('bugs_model')->get_thread($bug['id']);

        $this->view('public/bugs/show', $data);
    }

    /**
     * Handle the creation of a new bug report.
     *
     * @return void
     */
    public function create(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header("location: /login");
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $hash = $this->model('bugs_model')->generate_hash();

            $this->model('bugs_model')->insert('bugs', [
                'bug_hash'    => $hash,
                'user_id'     => $_SESSION['user_id'],
                'title'       => $_POST['title'],
                'description' => $_POST['description'],
                'status'      => 'open'
            ]);

            header("location: /bugs/show/" . substr($hash, 0, 6));
            exit;
        }

        $this->view('public/bugs/create');
    }

    /**
     * Add a comment/update to an existing bug.
     *
     * @param int|array $id The internal database ID of the bug.
     * @return void
     */
    public function comment($id): void
    {
        if (is_array($id)) {
            $id = $id[0];
        }

        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("location: /bugs");
            exit;
        }

        $bug = $this->model('bugs_model')->fetch(
            "select bug_hash from bugs where id = ?", 
            [$id]
        );

        if (!$bug) {
            header("location: /bugs");
            exit;
        }

        $this->model('bugs_model')->insert('bug_comments', [
            'bug_id'          => (int)$id,
            'user_id'         => $_SESSION['user_id'],
            'comment_text'    => $_POST['comment_text'],
            'is_admin_update' => (in_array($_SESSION['role'], ['admin', 'staff']) ? 1 : 0)
        ]);

        header("location: /bugs/show/" . substr($bug['bug_hash'], 0, 6));
        exit;
    }

    /**
     * Update the status of a bug (Admin/Staff only).
     *
     * @param int|array $id The internal database ID of the bug.
     * @return void
     */
    public function status($id): void
    {
        if (is_array($id)) {
            $id = $id[0];
        }

        $user_role = $_SESSION['role'] ?? 'user';
        $new_status = $_POST['status'] ?? 'open';

        if (!in_array($user_role, ['admin', 'staff']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("location: /bugs");
            exit;
        }

        if ($user_role === 'staff' && $new_status !== 'pending') {
            header("location: /bugs");
            exit;
        }

        $bug = $this->model('bugs_model')->fetch(
            "select bug_hash from bugs where id = ?", 
            [$id]
        );

        $this->model('bugs_model')->query(
            "UPDATE bugs SET status = ? WHERE id = ?", 
            [$new_status, $id]
        );

        header("location: /bugs/show/" . substr($bug['bug_hash'], 0, 6));
        exit;
    }
}
