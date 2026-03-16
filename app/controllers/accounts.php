<?php

class accounts extends controller
{
    // Designation as a Core Module prevents deletion from the site/DB
    public static $is_core = true;
    
    public function index()
    {
        $model = $this->model('accounts_model');

        $data['accounts'] = $model->get_all();

        $this->view('admin/accounts', $data);
    }
    
    /**
     * Admin entry point
     * Required so the admin index can see the module
     */
    public function admin()
    {
        $this->index();
    }

    
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/admin_accounts');
            exit;
        }

        $model = $this->model('accounts_model');

        $model->create([
            'username'     => trim($_POST['username']),
            'password'     => $_POST['password'],
            'display_name' => trim($_POST['display_name']),
            'user_level'   => (int)$_POST['user_level'],
            'is_active'    => isset($_POST['is_active']) ? 1 : 0
        ]);

        header('Location: ' . URLROOT . '/admin/accounts');
        exit;
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

        $model = $this->model('accounts_model');

        $model->update(
    'accounts',
    [
        'username' => trim($_POST['username']),
        'display_name' => trim($_POST['display_name']),
        'user_level' => (int)$_POST['user_level'],
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ],
    'id = :id',
    ['id' => $id]
);

        if (!empty($_POST['password'])) {
            $model->change_password($id, $_POST['password']);
        }

        header('Location: ' . URLROOT . '/admin/accounts');
        exit;
    }

    public function delete($id)
    {
        $model = $this->model('accounts_model');

        $model->delete($id);

        header('Location: ' . URLROOT . '/admin/accounts');
        exit;
    }
}
