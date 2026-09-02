<?php

/**
 * Site Configuration Controller
 *
 * Manages installation-specific site identity and mail configuration.
 *
 * Path: /app/controllers/site.php
 */

/* [AI:GPT-5.6 Sol | 2026-08-25 19:03:00 UTC] */
class site extends controller
{
    public static $is_core = true;

    /**
     * Site configuration admin page.
     *
     * @param array $params Route parameters.
     * @return void
     */
    public function admin($params = []): void
    {
        $this->require_admin(7);

        $siteFile = APPROOT . '/data/site.json';
        $mailerFile = APPROOT . '/data/mailer.json';
        $maintenanceFile = APPROOT . '/data/maintenance.lock';

        $siteConfig = $this->loadJson(
            $siteFile,
            [
                'name' => 'Chaos MVC',
                'copyright_name' => 'Chaos MVC',
                'author' => 'Chaos MVC',
                'description' => 'Lightweight Model View Controller',
                'keywords' => ''
            ]
        );

        $mailerConfig = $this->loadJson(
            $mailerFile,
            [
                'host' => '',
                'smtp_auth' => true,
                'username' => '',
                'password' => '',
                'encryption' => 'starttls',
                'port' => 587,
                'from_email' => '',
                'from_name' => ''
            ]
        );

        $data = [
            'site' => $siteConfig,
            'mailer' => $mailerConfig,
            'maintenance' => is_file($maintenanceFile),
            'success' => null,
            'error' => null
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->require_csrf();

            $section = (string) ($_POST['section'] ?? '');

            if ($section === 'site') {
                $result = $this->saveSiteConfig(
                    $siteFile,
                    $siteConfig
                );
            } elseif ($section === 'mail') {
                $result = $this->saveMailerConfig(
                    $mailerFile,
                    $mailerConfig
                );
            } elseif ($section === 'maintenance') {
                $result = $this->saveMaintenanceMode(
                    $maintenanceFile
                );
            } else {
                $result = [
                    'success' => false,
                    'message' => 'Invalid configuration section.'
                ];
            }

            if ($result['success']) {
                $data['success'] = $result['message'];

                $siteConfig = $this->loadJson(
                    $siteFile,
                    $siteConfig
                );

                $mailerConfig = $this->loadJson(
                    $mailerFile,
                    $mailerConfig
                );

                $data['site'] = $siteConfig;
                $data['mailer'] = $mailerConfig;
                $data['maintenance'] = is_file(
                    $maintenanceFile
                );

                $GLOBALS['SITE'] = array_replace(
                    $GLOBALS['SITE'] ?? [],
                    $siteConfig
                );
            } else {
                $data['error'] = $result['message'];
            }
        }

        $this->view('admin/site', $data);
    }

    /**
     * Enable or disable installation-owned maintenance mode.
     *
     * The updater uses a separate lock which this control cannot remove.
     *
     * @param string $file Site maintenance marker.
     * @return array
     */
    private function saveMaintenanceMode(string $file): array
    {
        $enabled = (string) ($_POST['enabled'] ?? '');

        if (!in_array($enabled, ['0', '1'], true)) {
            return [
                'success' => false,
                'message' => 'Invalid maintenance setting.'
            ];
        }

        if ($enabled === '0') {
            if (is_file($file) && !unlink($file)) {
                return [
                    'success' => false,
                    'message' => 'Maintenance mode could not be disabled.'
                ];
            }

            return [
                'success' => true,
                'message' => 'Maintenance mode disabled.'
            ];
        }

        $directory = dirname($file);

        if (
            !is_dir($directory)
            && !mkdir($directory, 0755, true)
            && !is_dir($directory)
        ) {
            return [
                'success' => false,
                'message' => 'Maintenance mode could not be enabled.'
            ];
        }

        $state = json_encode(
            [
                'maintenance' => true,
                'enabled_at' => gmdate('c')
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        $temporary = $file . '.tmp';

        if (
            $state === false
            || file_put_contents(
                $temporary,
                $state . PHP_EOL,
                LOCK_EX
            ) === false
            || !rename($temporary, $file)
        ) {
            if (is_file($temporary)) {
                @unlink($temporary);
            }

            return [
                'success' => false,
                'message' => 'Maintenance mode could not be enabled.'
            ];
        }

        @chmod($file, 0600);

        return [
            'success' => true,
            'message' => 'Maintenance mode enabled.'
        ];
    }

    /**
     * Save site identity configuration.
     *
     * @param string $file Configuration file.
     * @param array $current Current configuration.
     * @return array
     */
    private function saveSiteConfig(
        string $file,
        array $current
    ): array {
        $config = array_replace($current, [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'copyright_name' => trim(
                (string) ($_POST['copyright_name'] ?? '')
            ),
            'author' => trim((string) ($_POST['author'] ?? '')),
            'description' => trim(
                (string) ($_POST['description'] ?? '')
            ),
            'keywords' => trim(
                (string) ($_POST['keywords'] ?? '')
            )
        ]);

        if ($config['name'] === '') {
            return [
                'success' => false,
                'message' => 'Site name is required.'
            ];
        }

        if ($config['copyright_name'] === '') {
            $config['copyright_name'] = $config['name'];
        }

        if ($config['author'] === '') {
            $config['author'] = $config['name'];
        }

        if (!$this->writeJson($file, $config)) {
            return [
                'success' => false,
                'message' => 'Site configuration could not be written.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Site configuration updated.'
        ];
    }

    /**
     * Save mail configuration.
     *
     * A blank password preserves the currently stored password.
     *
     * @param string $file Configuration file.
     * @param array $current Current configuration.
     * @return array
     */
    private function saveMailerConfig(
        string $file,
        array $current
    ): array {
        $smtpAuth = isset($_POST['smtp_auth'])
            && $_POST['smtp_auth'] === '1';

        $port = filter_var(
            $_POST['port'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 65535
                ]
            ]
        );

        $encryption = (string) (
            $_POST['encryption'] ?? 'starttls'
        );

        $allowedEncryption = [
            '',
            'starttls',
            'smtps'
        ];

        if (!in_array(
            $encryption,
            $allowedEncryption,
            true
        )) {
            return [
                'success' => false,
                'message' => 'Invalid mail encryption setting.'
            ];
        }

        if ($port === false) {
            return [
                'success' => false,
                'message' => 'SMTP port must be between 1 and 65535.'
            ];
        }

        $fromEmail = trim(
            (string) ($_POST['from_email'] ?? '')
        );

        if (
            $fromEmail !== ''
            && !filter_var(
                $fromEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return [
                'success' => false,
                'message' => 'From email address is invalid.'
            ];
        }

        $password = (string) (
            $_POST['password'] ?? ''
        );

        if ($password === '') {
            $password = (string) (
                $current['password'] ?? ''
            );
        }

        $config = [
            'host' => trim(
                (string) ($_POST['host'] ?? '')
            ),
            'smtp_auth' => $smtpAuth,
            'username' => trim(
                (string) ($_POST['username'] ?? '')
            ),
            'password' => $password,
            'encryption' => $encryption,
            'port' => (int) $port,
            'from_email' => $fromEmail,
            'from_name' => trim(
                (string) ($_POST['from_name'] ?? '')
            )
        ];

        if (!$this->writeJson($file, $config)) {
            return [
                'success' => false,
                'message' => 'Mail configuration could not be written.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Mail configuration updated.'
        ];
    }

    /**
     * Load a JSON configuration file.
     *
     * @param string $file Configuration file.
     * @param array $defaults Default values.
     * @return array
     */
    private function loadJson(
        string $file,
        array $defaults
    ): array {
        if (!is_file($file)) {
            return $defaults;
        }

        $raw = file_get_contents($file);

        if ($raw === false) {
            return $defaults;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        return array_replace(
            $defaults,
            $decoded
        );
    }

    /**
     * Write JSON configuration atomically when possible.
     *
     * @param string $file Destination file.
     * @param array $data Configuration data.
     * @return bool
     */
    private function writeJson(
        string $file,
        array $data
    ): bool {
        $directory = dirname($file);

        if (!is_dir($directory)) {
            if (
                !mkdir($directory, 0755, true)
                && !is_dir($directory)
            ) {
                return false;
            }
        }

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            return false;
        }

        $temporary = $file . '.tmp';

        if (
            file_put_contents(
                $temporary,
                $json . PHP_EOL,
                LOCK_EX
            ) === false
        ) {
            return false;
        }

        if (!rename($temporary, $file)) {
            @unlink($temporary);
            return false;
        }

        @chmod($file, 0600);

        return true;
    }
}
/* [End AI:GPT-5.6 Sol] */
