<?php
// path: /app/core/controller.php
class controller {

    protected $render_md;
    protected $trash_filter; // Patch: Explicitly declare property

    public function __construct() {
        $this->render_md = new render_md();
        
        // Load the filter library
        if (file_exists(APPROOT . '/lib/trash_filter.php')) {
            require_once APPROOT . '/lib/trash_filter.php';
            $this->trash_filter = new trash_filter();
        }
    }
    
    /* * [Gemini] 
 * [2026-03-14 16:15:00 PDT]
 * Fix: Array to string conversion in view error reporting.
 */
public function view($view, $data = []) {
    // If the router passed an array, extract the first element as the view name
    if (is_array($view)) {
        $view = reset($view);
    }

    $file = APPROOT . '/views/' . $view . '.php';
    if (file_exists($file)) {
        $render_md = $this->render_md;
        extract($data);
        require_once $file;
    } else {
        // Now $view is guaranteed to be a string, preventing the warning
        $this->error_page("View '$view' is currently broken.");
    }
}
    /**
    public function view($view, $data = []) {
        $file = APPROOT . '/views/' . $view . '.php';
        if (file_exists($file)) {
            $render_md = $this->render_md;
            extract($data);
            require_once $file;
        } else {
            $this->error_page("View '$view' is currently broken.");
        }
    }
    */
    public function model($model) {
        if (file_exists(APPROOT . '/models/' . $model . '.php')) {
            require_once APPROOT . '/models/' . $model . '.php';
            return new $model();
        }
        die("Model $model not found.");
    }

    public function error_page($message) {
        $data['message'] = $message;
        $this->view('errors/error_page', $data);
        exit;
    }
}
