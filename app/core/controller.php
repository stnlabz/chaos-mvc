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
        'modules'
    ];

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
                extract($data);
            }

            require_once $file;
            return;
        }

        $this->error_page("View '{$view}' is currently broken.");
    }

    /**
     * Load Model
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
     */
    public function error_page($message) 
    {
        $data = ['message' => $message];
        $this->view('errors/error_page', $data);
        exit;
    }
}
