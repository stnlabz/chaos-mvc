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

        /*
         * CMSEC-2026-4830-A — Separated module discovery
         *
         * Core controllers remain discoverable under /app. The disabled
         * legacy behavior treated every controller there as a possible addon
         * and executed it while classifying ownership.
         */
        $all_controllers = glob(APPROOT . '/controllers/*.php');
        $system_files = ['admin.php', 'pages.php', 'auth.php', 'error_handler.php'];
        $modules_data = [];

        foreach ($all_controllers as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $system_files)) continue;

            require_once $file;
            if (class_exists($name, false)) {
                $reflect = new ReflectionClass($name);
                if (
                    $reflect->hasMethod('admin')
                    && $reflect->getMethod('admin')->isPublic()
                    && $reflect->hasProperty('is_core')
                    && $reflect->getStaticPropertyValue('is_core') === true
                ) {
                    $modules_data[] = [
                        'slug' => $name,
                        'is_core' => true,
                        'config' => []
                    ];
                }
            }
        }

        $moduleDirectories = glob(USERROOT . '/modules/*', GLOB_ONLYDIR) ?: [];

        foreach ($moduleDirectories as $directory) {
            $name = basename($directory);

            if (
                !preg_match('/^[a-z][a-z0-9_]{1,62}$/', $name)
                || is_link($directory)
            ) {
                continue;
            }

            $metadataPath = $directory . '/module.json';
            $controllerPath = $directory . '/controllers/' . $name . '.php';
            $raw = is_file($metadataPath)
                ? file_get_contents($metadataPath)
                : false;
            $metadata = is_string($raw) ? json_decode($raw, true) : null;

            if (
                !is_array($metadata)
                || (string) ($metadata['module'] ?? '') !== $name
                || !is_file($controllerPath)
                || !$this->moduleDeclaresPublicAdmin($name)
            ) {
                continue;
            }

            $modules_data[] = [
                'slug' => $name,
                'is_core' => false,
                'config' => $metadata
            ];
        }

        $data['modules'] = $modules_data;
        $this->view('admin/modules', $data);
    }
}
