<?php

/** Installed theme administration. */
class themes extends controller
{
    public static $is_core = true;

    public function admin($params = []): void
    {
        $this->require_admin(7);
        $message = null;
        $error = null;
        /* [AI:GPT-5.6 Sol | 2026-09-04 14:04:16 UTC] */
        $updater = new theme_updater();
        /* [End AI:GPT-5.6 Sol] */

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();
            /* [AI:GPT-5.6 Sol | 2026-09-04 14:04:16 UTC] */
            $action = $_POST['action'] ?? 'apply';
            if ($action !== 'apply') {
                // Same authority as module installation, through the existing Admin route.
                $this->require_admin(9);
                header('Content-Type: application/json');
                header('Cache-Control: no-store');
                try {
                    if (!in_array($action, ['check_update', 'update', 'rollback'], true)
                        || !is_string($_POST['theme'] ?? null)) {
                        throw new RuntimeException('Invalid theme maintenance action.');
                    }
                    $slug = trim($_POST['theme']);
                    if ($action === 'rollback' && ($_POST['confirm_rollback'] ?? '') !== '1') {
                        throw new RuntimeException('Confirm replacement with the previous theme files.');
                    }
                    $result = match ($action) {
                        'check_update' => $updater->check($slug),
                        'update' => $updater->update($slug),
                        'rollback' => $updater->rollback($slug),
                    };
                } catch (Throwable $exception) {
                    $result = ['success' => false, 'error' => $exception->getMessage()];
                }
                echo json_encode($result);
                exit;
            }
            /* [End AI:GPT-5.6 Sol] */
            $selected = trim((string) ($_POST['theme'] ?? ''));

            if ($selected !== '' && theme::details($selected) === null) {
                $error = 'The selected theme is invalid or incomplete.';
            } elseif ($this->saveActiveTheme($selected)) {
                $GLOBALS['SITE']['active_theme'] = $selected;
                $message = $selected === ''
                    ? 'The Core theme is active.'
                    : 'The selected theme is active.';
            } else {
                $error = 'The active theme could not be saved.';
            }
        }

        /* [AI:GPT-5.6 Sol | 2026-09-04 14:04:16 UTC] */
        try {
            $installed = $updater->installed();
        } catch (Throwable $exception) {
            $installed = [];
            $error = $exception->getMessage();
        }
        $this->view('admin/themes', [
            'themes' => $installed,
            'can_update' => (int) ($_SESSION['user_level'] ?? 0) >= 9,
            'active_theme' => theme::activeSlug(),
            'message' => $message,
            'error' => $error,
        ]);
        /* [End AI:GPT-5.6 Sol] */
    }

    private function saveActiveTheme(string $slug): bool
    {
        $file = APPROOT . '/data/site.json';
        $raw = is_file($file) ? file_get_contents($file) : false;
        $site = is_string($raw) ? json_decode($raw, true) : [];

        if (!is_array($site)) {
            return false;
        }

        $site['active_theme'] = $slug;
        $json = json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        $directory = dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        $temporary = $file . '.tmp';

        if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            return false;
        }

        if (!rename($temporary, $file)) {
            if (!copy($temporary, $file)) {
                @unlink($temporary);
                return false;
            }

            @unlink($temporary);
        }

        @chmod($file, 0600);
        return true;
    }
}
