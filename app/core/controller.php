<?php
// path: /app/core/controller.php

class controller {

    protected $render_md;
    protected $trash_filter;

    /**
     * Core Modules (System Protected)
     */
    protected const CORE_MODULES = [
        'traffic',
        'posts',
        'media',
        'accounts',
        'health',
        'modules',
        'site',
        'updater'
    ];

    /**
	 * CSRF Token
	 * @api
	*/
    public function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }

    protected function verify_csrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';

        if (!is_string($token) || !hash_equals($this->csrf_token(), $token)) {
            http_response_code(403);
            $this->error_page('The request could not be verified. Please try again.');
        }
    }

    protected function require_csrf(): void
    {
        $this->verify_csrf();
    }
	
	/**
	 * CSRF Field
	 * @api
	*/

    public function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($this->csrf_token(), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    protected function require_admin(int $level = 7): void
    {
        if ((int) ($_SESSION['user_level'] ?? 0) < $level) {
            header('Location: /login');
            exit;
        }
    }

    /**
     * Check if module is core
     */
    protected function isCore(string $module): bool
    {
        return in_array($module, self::CORE_MODULES, true);
    }

    public function __construct() 
    {
        $this->render_md = new render_md();

        // Load optional filter
        if (file_exists(APPROOT . '/lib/trash_filter.php')) {
            require_once APPROOT . '/lib/trash_filter.php';
            $this->trash_filter = new trash_filter();
        }
    }

    /**
     * Render View
	 * @api
     */
    public function view($view, $data = []) 
    {
        // Router safety (array → string)
        if (is_array($view)) {
            $view = reset($view);
        }

        $file = APPROOT . '/views/' . $view . '.php';

        if (file_exists($file)) {
            $render_md = $this->render_md;

            if (is_array($data)) {
                extract($data, EXTR_SKIP);
            }

            require_once $file;
            return;
        }

        $this->error_page("View '{$view}' is currently broken.");
    }

    /**
     * Load Model
	 * @api
     */
    public function model($model) 
    {
        $file = APPROOT . '/models/' . $model . '.php';

        if (file_exists($file)) {
            require_once $file;
            return new $model();
        }

        die("Model {$model} not found.");
    }

    /**
     * Error Page
	 * @api
     */
    public function error_page($message) 
    {
        $data = ['message' => $message];
        $this->view('errors/error_page', $data);
        exit;
    }
}
