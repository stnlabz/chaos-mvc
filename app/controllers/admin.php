<?php
// path: /app/controllers/admin.php

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
class admin extends controller
{
    public function index($params = []) 
    {
        $this->require_admin(7);

        $slug = $params[0] ?? null;

        if (in_array($slug, ['update', 'uninstall'], true)) {
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

    $this->require_admin(9);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'POST required']);
        exit;
    }

    $this->verify_csrf();

    $module = $_POST['module'] ?? '';

    if (!is_string($module) || !preg_match('/^[a-z0-9_]+$/', $module)) {
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

    if (!is_array($config)) {
        echo json_encode(['success' => false, 'error' => 'Invalid module config']);
        exit;
    }

    $current   = $config['version'] ?? '0.0.0';
    $updateUrl = $config['update_url'] ?? null;

    if (!$this->isSafeRemoteUrl($updateUrl)) {
        echo json_encode(['success' => false, 'error' => 'No update URL']);
        exit;
    }

    // 🌐 Fetch update metadata
    $remoteRaw = @file_get_contents($updateUrl, false, null, 0, 1048577);

    if (!$remoteRaw || strlen($remoteRaw) > 1048576) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch update source']);
        exit;
    }

    $remote = json_decode($remoteRaw, true);

    if (!is_array($remote)) {
        echo json_encode(['success' => false, 'error' => 'Invalid update metadata']);
        exit;
    }

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

    $expectedHash = strtolower((string) ($remote['sha256'] ?? ''));

    if (!$this->isSafeRemoteUrl($download) || !preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
        echo json_encode(['success' => false, 'error' => 'No package URL']);
        exit;
    }

    // 📦 temp paths
    $temporaryId = bin2hex(random_bytes(12));
    $tmpZip = sys_get_temp_dir() . '/chaos_' . $temporaryId . '.zip';
    $tmpDir = sys_get_temp_dir() . '/chaos_' . $temporaryId;

    // 🧲 download zip
    $zipData = @file_get_contents($download, false, null, 0, 20971521);

    if (!$zipData || strlen($zipData) > 20971520) {
        echo json_encode(['success' => false, 'error' => 'Download failed']);
        exit;
    }

    if (!hash_equals($expectedHash, hash('sha256', $zipData))) {
        echo json_encode(['success' => false, 'error' => 'Package checksum failed']);
        exit;
    }

    file_put_contents($tmpZip, $zipData);

    // 📂 extract
    $zip = new ZipArchive;

    if ($zip->open($tmpZip) === true && $this->isSafeModuleArchive($zip)) {
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
        $this->require_admin(9);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /admin");
            exit;
        }

        $this->verify_csrf();

        $module = $_POST['module'] ?? '';

        if (!is_string($module) || !preg_match('/^[a-z0-9_]+$/', $module)) {
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

    /**
     * Accept only HTTPS update endpoints and reject literal private IPs.
     */
    private function isSafeRemoteUrl($url): bool
    {
        if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        return strtolower($host) !== 'localhost';
    }

    /**
     * Reject traversal, links, and unexpected module package paths.
     */
    private function isSafeModuleArchive(ZipArchive $zip): bool
    {
        $allowedRoots = ['controllers', 'models', 'views'];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);
            $name = str_replace('\\', '/', (string) ($entry['name'] ?? ''));
            $segments = explode('/', trim($name, '/'));
            $attributes = 0;
            $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes);
            $unixType = ($attributes >> 16) & 0170000;

            if (
                $name === '' ||
                str_starts_with($name, '/') ||
                str_contains($name, '../') ||
                str_contains($name, ':') ||
                $unixType === 0120000 ||
                !in_array($segments[0] ?? '', $allowedRoots, true)
            ) {
                $zip->close();
                return false;
            }

            if (!str_ends_with($name, '/') && strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'php') {
                $zip->close();
                return false;
            }

            if (($entry['size'] ?? 0) > 2097152) {
                $zip->close();
                return false;
            }
        }

        return true;
    }
}
/* [End AI:GPT-5.6 Sol] */
