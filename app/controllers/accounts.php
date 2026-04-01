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
        $this->index();
    }

    /**
     * Create a new account
     *
     * @return void
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

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
            empty($data['email_address'])
        ) {
            $_SESSION['msg'] = 'Missing required fields';
            $_SESSION['msg_type'] = 'danger';

            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

        $result = $model->create($data);

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
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

        $id = is_array($params) ? ($params[0] ?? null) : $params;

        if (!$id || empty($_POST['email_address'])) {
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
}
