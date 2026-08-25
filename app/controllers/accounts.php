<?php

/**
 * Accounts Controller
 *
 * Handles all account-related admin operations.
 */
class accounts extends controller
{
    /**
     * Core module protection flag
     *
     * @var bool
     */
    public static $is_core = true;

    /**
     * Display all accounts
     *
     * @return void
     */
    public function index()
    {
        $this->require_admin(9);
        $model = $this->model('accounts_model');

        $data = [
            'accounts' => $model->get_all()
        ];

        $this->view('admin/accounts', $data);
    }

    /**
     * Admin entry point
     *
     * @return void
     */
    public function admin()
    {
        $this->require_admin(9);
        $this->index();
    }

    /**
     * Create a new account
     *
     * @return void
     */
    public function create()
    {
        $this->require_admin(9);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

        $this->verify_csrf();
        $model = $this->model('accounts_model');

        $data = [
            'username'      => trim($_POST['username'] ?? ''),
            'password'      => $_POST['password'] ?? '',
            'display_name'  => trim($_POST['display_name'] ?? ''),
            'email_address' => trim($_POST['email_address'] ?? ''),
            'user_level'    => (int)($_POST['user_level'] ?? 1)
        ];

        if (
            empty($data['username']) ||
            empty($data['password']) ||
            empty($data['email_address']) ||
            !filter_var($data['email_address'], FILTER_VALIDATE_EMAIL) ||
            strlen($data['password']) < 12 ||
            !in_array($data['user_level'], [1, 9], true)
        ) {
            $_SESSION['msg'] = 'Missing required fields';
            $_SESSION['msg_type'] = 'danger';

            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

        try {
            $result = $model->create($data);
        } catch (PDOException $e) {
            $result = false;
        }

        $_SESSION['msg'] = $result
            ? 'Account created successfully'
            : 'Account creation failed';

        $_SESSION['msg_type'] = $result ? 'success' : 'danger';

        header('Location: ' . URLROOT . '/admin/accounts');
        exit;
    }

    /**
     * Delete an account
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $this->require_admin(9);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

        $this->verify_csrf();
        $id = is_array($id) ? ($id[0] ?? 0) : $id;

        if ((int) $id === (int) ($_SESSION['user_id'] ?? 0)) {
            $_SESSION['msg'] = 'You cannot delete your own account.';
            $_SESSION['msg_type'] = 'danger';
            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }
        $model = $this->model('accounts_model');

        $model->delete((int)$id);

        header('Location: ' . URLROOT . '/admin/accounts');
        exit;
    }

    /**
     * Update account email address
     *
     * @param array|int $params
     * @return void
     */
    public function email($params = [])
    {
        $this->require_admin(9);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

        $this->verify_csrf();
        $id = is_array($params) ? ($params[0] ?? null) : $params;

        if (!$id || empty($_POST['email_address']) || !filter_var($_POST['email_address'], FILTER_VALIDATE_EMAIL)) {
            $_SESSION['msg'] = 'Invalid email update request';
            $_SESSION['msg_type'] = 'danger';

            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

        $model = $this->model('accounts_model');

        $result = $model->update_email(
            (int)$id,
            trim($_POST['email_address'])
        );

        $_SESSION['msg'] = $result
            ? 'Email updated successfully'
            : 'Email update failed';

        $_SESSION['msg_type'] = $result ? 'success' : 'danger';

        header('Location: ' . URLROOT . '/admin/accounts');
        exit;
    }

    public function password($params = []): void
    {
        $this->require_admin(9);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

        $this->verify_csrf();
        $id = is_array($params) ? ($params[0] ?? 0) : $params;
        $password = $_POST['password'] ?? '';

        if ((int) $id < 1 || !is_string($password) || strlen($password) < 12) {
            $_SESSION['msg'] = 'Passwords must contain at least 12 characters.';
            $_SESSION['msg_type'] = 'danger';
            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

        $this->model('accounts_model')->change_password((int) $id, $password);
        $_SESSION['msg'] = 'Password changed successfully.';
        $_SESSION['msg_type'] = 'success';
        header('Location: ' . URLROOT . '/admin/accounts');
        exit;
    }
}
