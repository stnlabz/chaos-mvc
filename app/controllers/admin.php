<?php
// path: /app/controllers/admin.php

class admin extends controller 
{
    /**
     * Updated Admin Index
     * Handles the Router Patch by capturing segments in $params.
     * @param array $params Incoming URL segments from the router
     */
    public function index($params = []) 
    {
        if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] < 7) {
            header("Location: /auth/login");
            exit;
        }

        // Segment after /admin/ is the first param
        $slug = $params[0] ?? null;

        // 1. Check for local methods (like refresh_indices)
        if ($slug && method_exists($this, $slug) && $slug !== 'index') {
            $this->$slug($params);
            return;
        }

        // 2. Delegate to external module controllers
        $path = APPROOT . '/controllers/' . $slug . '.php';
        if ($slug && file_exists($path)) {
            require_once $path;
            if (class_exists($slug)) {
                $controller = new $slug();
                if (method_exists($controller, 'admin')) {
                    $controller->admin($params);
                    return;
                }
            }
        }

        $this->view('admin/index');
    }

    /**
     * Discovery and SEO Index Refresh
     * Signature includes $params to satisfy the router's reflection check.
     */
    public function refresh_indices($params = []) 
    {
        if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] < 9) {
            header("Location: /admin");
            exit;
        }

        $module_model = $this->model('modules_model');
        $directory = APPROOT . '/controllers/';
        $files = glob($directory . '*.php');
        
         /* [Human:Mei | 2026-03-11 01:32:00 UTC] */
        /**
         * Removing all non poemei.com restricted controllers
        $restricted = [
            'sentinel', 'page', 'admin', 'auth', 'controller', 'llms', 'ror', 'sitemap',
            'rolls', 'offices', 's', 'modules', 'accounts', 'error_handler', 'health', 'lessons'
        ];
        */
        /* [End Human:Mei] */

        $restricted = [
            'sentinel', 'page', 'admin', 'auth', 'controller', 'llms', 'ror', 'sitemap', 's', 'modules', 'accounts', 'error_handler', 'health'
        ];

        foreach ($files as $file) {
            $slug = basename($file, '.php');

            if (in_array($slug, $restricted)) continue;
            
            $existing = $module_model->get_by_slug($slug);
             
             /* [Human:Mei | 2026-03-10 18:32:00 UTC] */
             // Commented out CRUD operations to prevent unsanctioned DB writes
             // during SEO refresh cycle.
            // Simple discovery: register file in DB if missing.
            /**
            if (!$existing) {
                $module_model->insert('modules', [
                    'slug' => $slug,
                    'title' => ucfirst($slug), 
                    'content' => ''
                ]);
            }
            
            // SQUIRE_CRAWL REMOVED
            */
            /* [End Human:Mei] */
        }

        // Trigger SEO automated tools
        $tools = ['sitemap', 'ror', 'llms'];
        foreach ($tools as $tool) {
            $path = APPROOT . '/controllers/' . $tool . '.php';
            if (file_exists($path)) {
                require_once $path;
                if (class_exists($tool)) {
                    $instance = new $tool();
                    if (method_exists($instance, 'index')) { 
                        ob_start();
                        $instance->index(); 
                        ob_end_clean();
                    }
                }
            }
        }

        $_SESSION['admin_status'] = 'Sync complete. Indices refreshed.';
        session_write_close();
        header("Location: /admin");
        exit;
    }
    
    public function uninstall() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $module = $_POST['module'];
            require_once APPROOT . '/controllers/' . $module . '.php';
            if (property_exists($module, 'is_core') && $module::$is_core) {
                header("Location: /admin");
                exit;
            }

            $this->db->query("DROP TABLE IF EXISTS " . $module);
            $backend = [APPROOT . "/controllers/" . $module . ".php", APPROOT . "/models/" . $module . "_model.php"];
            foreach ($backend as $file) { if (file_exists($file)) unlink($file); }
            
            $admin_view = APPROOT . "/views/admin/" . $module . ".php";
            if (file_exists($admin_view)) unlink($admin_view);
            
            $public_dir = APPROOT . "/views/public/" . $module;
            if (is_dir($public_dir)) { $this->recursive_rmdir($public_dir); }

            header("Location: /admin");
            exit;
        }
    }

    private function recursive_rmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) $this->recursive_rmdir($dir . "/" . $object);
                    else unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }
}
