<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
class core_updates extends controller
{
    public function index(): void
    {
        $this->require_admin(9);
        header('Location: /admin/core_updates');
        exit;
    }

    /**
     * Core updater administration entry point.
     *
     * @param array<int, string> $params
     */
    public function admin($params = []): void
    {
        $this->require_admin(9);
        $action = $params[1] ?? '';

        if ($action === 'check') {
            $this->check();
            return;
        }

        if ($action === 'validate_package') {
            $this->validatePackage();
            return;
        }

        if ($action === 'install') {
            $this->install();
            return;
        }

        if ($action === 'recover') {
            $this->recover();
            return;
        }

        require_once APPROOT . '/core/version.php';
        $root = dirname(APPROOT);
        $stateDirectory = $root . '/.chaos-update';
        $maintenanceState = ['active' => false];
        $lockState = null;
        $rollbackState = null;

        try {
            foreach (['core_backup_manager', 'core_maintenance', 'core_update_lock'] as $requiredClass) {
                if (!class_exists($requiredClass)) {
                    throw new RuntimeException("Required Core updater component is unavailable: {$requiredClass}.");
                }
            }

            $backupManager = new core_backup_manager($root, $stateDirectory);
            $maintenanceState = (new core_maintenance($stateDirectory))->read();
            $lockState = (new core_update_lock($stateDirectory))->read();
            $rollbackState = $backupManager->retainedManifest();
        } catch (Throwable $exception) {
            $_SESSION['core_update_result'] = [
                'success' => false,
                'outcome' => 'failed_unchanged',
                'phase' => 'readiness',
                'error_code' => 'core_runtime_unavailable',
                'message' => $exception->getMessage()
            ];
        }

        $this->view('admin/core_updates', [
            'installed_version' => defined('CHAOS_VERSION') ? CHAOS_VERSION : '0.0.0',
            'result' => $_SESSION['core_update_result'] ?? null,
            'offer' => $_SESSION['core_update_offer'] ?? null,
            'stage' => $_SESSION['core_update_stage'] ?? null,
            'maintenance' => $maintenanceState,
            'lock' => $lockState,
            'rollback' => $rollbackState
        ]);
        unset($_SESSION['core_update_result']);
    }

    private function check(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->error_page('POST required.');
        }

        $this->verify_csrf();
        require_once APPROOT . '/core/version.php';
        $keyPath = APPROOT . '/core/core_update_public_key.pem';
        $publicKey = is_file($keyPath) ? (string) file_get_contents($keyPath) : '';
        $updater = new core_updater(
            defined('CHAOS_VERSION') ? CHAOS_VERSION : '0.0.0',
            $publicKey
        );

        $result = $updater->check();
        $_SESSION['core_update_result'] = $result;

        if (($result['outcome'] ?? null) === 'update_available' && is_array($result['offer'] ?? null)) {
            $_SESSION['core_update_offer'] = $result['offer'];
        } else {
            unset($_SESSION['core_update_offer']);
        }

        header('Location: /admin/core_updates');
        exit;
    }

    private function validatePackage(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->error_page('POST required.');
        }

        $this->verify_csrf();
        $offer = $_SESSION['core_update_offer'] ?? null;

        if (!is_array($offer)) {
            $_SESSION['core_update_result'] = [
                'success' => false,
                'outcome' => 'failed_unchanged',
                'error_code' => 'core_offer_missing',
                'message' => 'Check for a Core update before validating its package.'
            ];
            header('Location: /admin/core_updates');
            exit;
        }

        $root = dirname(APPROOT);
        $stager = new core_package_stager($root, $root . '/.chaos-update');
        $result = $stager->stage($offer);
        $_SESSION['core_update_result'] = $result;

        if (($result['outcome'] ?? null) === 'package_staged') {
            $_SESSION['core_update_stage'] = [
                'operation_id' => $result['operation_id'],
                'target_version' => $result['target_version']
            ];
            unset($_SESSION['core_update_offer']);
        }

        header('Location: /admin/core_updates');
        exit;
    }

    private function install(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->error_page('POST required.');
        }

        $this->verify_csrf();
        $stage = $_SESSION['core_update_stage'] ?? null;

        if (!is_array($stage) || !is_string($stage['operation_id'] ?? null)) {
            $_SESSION['core_update_result'] = [
                'success' => false,
                'outcome' => 'failed_unchanged',
                'error_code' => 'core_stage_missing',
                'message' => 'Validate a Core package before installation.'
            ];
            header('Location: /admin/core_updates');
            exit;
        }

        try {
            if (!extension_loaded('pdo_mysql')) {
                throw new RuntimeException('The PHP PDO MySQL extension is required for Core installation.');
            }

            $database = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            $root = dirname(APPROOT);
            $engine = new core_update_engine(
                $root,
                $root . '/.chaos-update',
                new pdo_core_migration_database($database)
            );
            $result = $engine->install($stage['operation_id']);
        } catch (Throwable $exception) {
            $result = [
                'success' => false,
                'outcome' => 'failed_unchanged',
                'phase' => 'preflight',
                'error_code' => 'core_database_unavailable',
                'message' => $exception->getMessage()
            ];
        }

        $_SESSION['core_update_result'] = $result;

        if (($result['outcome'] ?? null) === 'updated') {
            unset($_SESSION['core_update_stage']);
        }

        header('Location: /admin/core_updates');
        exit;
    }

    private function recover(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->error_page('POST required.');
        }

        $this->verify_csrf();
        $root = dirname(APPROOT);
        $service = new core_recovery_service($root, $root . '/.chaos-update');
        $_SESSION['core_update_result'] = $service->recover();
        unset($_SESSION['core_update_stage'], $_SESSION['core_update_offer']);
        header('Location: /admin/core_updates');
        exit;
    }
}
/* [End AI:GPT-5.6 Sol] */
