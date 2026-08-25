<?php
// path: /app/controllers/page.php
class page extends controller {
    // Designation as a Core Module prevents deletion from the site/DB
    public static $is_core = true;
    
    public function index($slug = 'home') {
        $controllerName = is_array($slug) ? ($slug[0] ?? 'home') : $slug;

        if (file_exists(APPROOT . '/controllers/' . $controllerName . '.php')) {
            require_once APPROOT . '/controllers/' . $controllerName . '.php';
            $controller = new $controllerName;
            $controller->index($slug); 
            return;
        }

        $module = $this->model('modules_model')->get_by_slug($controllerName);
        if ($module) {
            // Updated path to /public/page/dynamic.php
            $this->view('public/page/dynamic', $module);
            return;
        }

        require_once APPROOT . '/controllers/error_handler.php';
        (new error_handler())->not_found();
        
        // Inside page.php, right before the file_exists check:
echo "DEBUG: Looking for: " . APPROOT . '/controllers/' . $controllerName . '.php';
    }
}
