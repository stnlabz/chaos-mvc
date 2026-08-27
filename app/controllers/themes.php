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

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();
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

        $this->view('admin/themes', [
            'themes' => theme::installed(),
            'active_theme' => theme::activeSlug(),
            'message' => $message,
            'error' => $error,
        ]);
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
