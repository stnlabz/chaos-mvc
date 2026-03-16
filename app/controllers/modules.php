<?php
// path: /app/controllers/modules.php

class modules extends controller
{
    public static $is_core = true;

    public function admin($url = [])
    {
        if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] < 9) {
            header("Location: /admin");
            exit;
        }

        // SCAN: Check the filesystem for all active controllers
        $all_controllers = glob(APPROOT . '/controllers/*.php');
        $system_files = ['admin.php', 'pages.php', 'auth.php', 'error_handler.php'];
        $modules_data = [];

        foreach ($all_controllers as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $system_files)) continue;

            require_once $file;
            if (class_exists($name)) {
                $reflect = new ReflectionClass($name);
                if ($reflect->hasMethod('admin')) {
                    // SEPARATION: Core vs. User Mod
                    $is_core = $reflect->hasProperty('is_core') ? $reflect->getStaticPropertyValue('is_core') : false;
                    
                    $modules_data[] = [
                        'slug' => $name,
                        'is_core' => $is_core
                    ];
                }
            }
        }

        $data['modules'] = $modules_data;
        $this->view('admin/modules', $data);
    }
}
