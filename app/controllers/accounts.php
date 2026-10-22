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
        $model->ensure_admin_module_access_schema();

        $data = [
            'accounts' => $model->get_all(),
            'admin_modules' => $this->discoverAssignableAdminModules(),
            'module_access' => $model->get_admin_module_access_map(),
        ];

        $this->view('admin/accounts', $data);
    }

    /**
     * Admin entry point
     *
     * @return void
     */
    public function admin($params = [])
    {
        $this->require_admin(9);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->updateAccessProfile();
            return;
        }

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
            'role'          => strtolower(trim((string) ($_POST['role'] ?? 'user'))),
        ];

        if (
            empty($data['username']) ||
            empty($data['password']) ||
            empty($data['email_address']) ||
            !filter_var($data['email_address'], FILTER_VALIDATE_EMAIL) ||
            strlen($data['password']) < 12 ||
            !in_array($data['role'], ['user', 'developer', 'ai', 'admin'], true)
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

    private function updateAccessProfile(): void
    {
        $this->verify_csrf();

        if (($_POST['action'] ?? '') !== 'access') {
            (new error_handler())->bad_request();
        }

        $accountId = filter_var(
            $_POST['account_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $role = strtolower(trim((string) ($_POST['role'] ?? '')));
        $requestedModules = $_POST['modules'] ?? [];

        if (
            $accountId === false
            || !in_array($role, ['user', 'developer', 'ai', 'admin'], true)
            || !is_array($requestedModules)
        ) {
            (new error_handler())->bad_request();
        }

        $model = $this->model('accounts_model');
        $account = $model->get_by_id((int) $accountId);

        if (!is_array($account)) {
            (new error_handler())->not_found();
        }

        if (
            (int) $accountId === (int) ($_SESSION['user_id'] ?? 0)
            && $role !== 'admin'
        ) {
            $_SESSION['msg'] = 'You cannot remove your own administrator authority.';
            $_SESSION['msg_type'] = 'danger';
            header('Location: ' . URLROOT . '/admin/accounts');
            exit;
        }

        $available = $this->discoverAssignableAdminModules();
        $modules = [];

        foreach ($requestedModules as $module) {
            if (!is_string($module) || !in_array($module, $available, true)) {
                (new error_handler())->bad_request();
            }
            $modules[] = $module;
        }

        try {
            $updated = $model->update_admin_access_profile(
                (int) $accountId,
                $role,
                $modules
            );
        } catch (Throwable $error) {
            error_log('Account Admin access update failed: ' . $error->getMessage());
            $updated = false;
        }

        $_SESSION['msg'] = $updated
            ? 'Account classification and module access updated.'
            : 'Account access update failed.';
        $_SESSION['msg_type'] = $updated ? 'success' : 'danger';
        header('Location: ' . URLROOT . '/admin/accounts');
        exit;
    }

    /** @return array<int, string> */
    private function discoverAssignableAdminModules(): array
    {
        $modules = [];
        $excluded = ['admin', 'auth', 'error_handler', 'install', 'pages'];

        foreach (glob(APPROOT . '/controllers/*.php') ?: [] as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $excluded, true) || !admin_access::isAssignable($name)) {
                continue;
            }

            require_once $file;
            if (class_exists($name, false) && method_exists($name, 'admin')) {
                $method = new ReflectionMethod($name, 'admin');
                if ($method->isPublic()) {
                    $modules[] = $name;
                }
            }
        }

        foreach (glob(USERROOT . '/modules/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);
            $metadataPath = $directory . '/module.json';
            $raw = is_file($metadataPath) ? file_get_contents($metadataPath) : false;
            $metadata = is_string($raw) ? json_decode($raw, true) : null;

            if (
                admin_access::isAssignable($name)
                && !is_link($directory)
                && is_array($metadata)
                && (string) ($metadata['module'] ?? '') === $name
                && is_file($directory . '/controllers/' . $name . '.php')
                && $this->moduleDeclaresPublicAdmin($name)
            ) {
                $modules[] = $name;
            }
        }

        sort($modules, SORT_STRING);
        return array_values(array_unique($modules));
    }
}
