<?php
// path: /app/controllers/admin.php

class admin extends controller 
{
    public function index($params = []) 
    {
        if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] < 7) {
            header("Location: /auth/login");
            exit;
        }

        $slug = $params[0] ?? null;

        if ($slug && method_exists($this, $slug) && $slug !== 'index') {
            $this->$slug($params);
            return;
        }

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
    
    public function update(): void
{
    header('Content-Type: application/json');

    $module = $_GET['module'] ?? '';

    if (!$module) {
        echo json_encode(['success' => false]);
        exit;
    }

    // 🔒 Block core
    if ($this->isCore($module)) {
        echo json_encode(['success' => false]);
        exit;
    }

    $configPath = APPROOT . '/data/modules/' . $module . '.json';

    if (!file_exists($configPath)) {
        echo json_encode(['success' => false]);
        exit;
    }

    $config = json_decode(file_get_contents($configPath), true);

    $current   = $config['version'] ?? '0.0.0';
    $updateUrl = $config['update_url'] ?? null;

    if (!$updateUrl) {
        echo json_encode(['success' => false, 'error' => 'No update URL']);
        exit;
    }

    // 🌐 Fetch update metadata
    $remoteRaw = @file_get_contents($updateUrl);

    if (!$remoteRaw) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch update source']);
        exit;
    }

    $remote = json_decode($remoteRaw, true);

    $new = $remote['version'] ?? $current;

    // 🧠 Already up to date
    if (!version_compare($new, $current, '>')) {
        echo json_encode([
            'success' => true,
            'version' => $current,
            'message' => 'Already up to date'
        ]);
        exit;
    }

    $download = $remote['download'] ?? null;

    if (!$download) {
        echo json_encode(['success' => false, 'error' => 'No package URL']);
        exit;
    }

    // 📦 temp paths
    $tmpZip = APPROOT . '/../tmp_' . $module . '.zip';
    $tmpDir = APPROOT . '/../tmp_' . $module;

    // 🧲 download zip
    $zipData = @file_get_contents($download);

    if (!$zipData) {
        echo json_encode(['success' => false, 'error' => 'Download failed']);
        exit;
    }

    file_put_contents($tmpZip, $zipData);

    // 📂 extract
    $zip = new ZipArchive;

    if ($zip->open($tmpZip) === TRUE) {
        $zip->extractTo($tmpDir);
        $zip->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Zip extract failed']);
        exit;
    }

    // 🔁 overwrite (ONLY if folders exist)
    $this->recursive_copy($tmpDir . '/controllers', APPROOT . '/controllers');
    $this->recursive_copy($tmpDir . '/models', APPROOT . '/models');
    $this->recursive_copy($tmpDir . '/views', APPROOT . '/views');

    // 🧹 cleanup
    if (file_exists($tmpZip)) unlink($tmpZip);
    $this->recursive_rmdir($tmpDir);

    // 🧾 update version
    file_put_contents($configPath, json_encode([
        'version'    => $new,
        'update_url' => $updateUrl
    ], JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true,
        'version' => $new
    ]);

    exit;
}

    private function recursive_copy($src, $dst)
{
    if (!is_dir($src)) return;

    @mkdir($dst, 0755, true);

    foreach (scandir($src) as $file) {
        if ($file === '.' || $file === '..') continue;

        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";

        if (is_dir($srcPath)) {
            $this->recursive_copy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
}

    public function uninstall(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /admin");
            exit;
        }

        $module = $_POST['module'] ?? '';

        if (!$module) {
            $this->error_page('Invalid module.');
        }

        if ($this->isCore($module)) {
            $this->error_page('Core modules cannot be removed.');
        }

        $config = APPROOT . '/data/modules/' . $module . '.json';
        if (file_exists($config)) {
            unlink($config);
        }

        if (isset($this->db)) {
            $this->db->query("DROP TABLE IF EXISTS `" . $module . "`");
        }

        $backend = [
            APPROOT . "/controllers/" . $module . ".php",
            APPROOT . "/models/" . $module . "_model.php"
        ];

        foreach ($backend as $file) {
            if (file_exists($file)) unlink($file);
        }

        $admin_view = APPROOT . "/views/admin/" . $module . ".php";
        if (file_exists($admin_view)) unlink($admin_view);

        $public_dir = APPROOT . "/views/public/" . $module;
        if (is_dir($public_dir)) {
            $this->recursive_rmdir($public_dir);
        }

        header("Location: /admin/modules");
        exit;
    }

    private function recursive_rmdir($dir): void
    {
        if (!is_dir($dir)) return;

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->recursive_rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
