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

        require_once APPROOT . '/core/version.php';
        $this->view('admin/core_updates', [
            'installed_version' => defined('CHAOS_VERSION') ? CHAOS_VERSION : '0.0.0',
            'result' => $_SESSION['core_update_result'] ?? null,
            'offer' => $_SESSION['core_update_offer'] ?? null
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
            unset($_SESSION['core_update_offer']);
        }

        header('Location: /admin/core_updates');
        exit;
    }
}
/* [End AI:GPT-5.6 Sol] */
