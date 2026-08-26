<?php

/**
 * Mailer Factory
 *
 * CMSEC-2026-4829 — Installation SMTP Configuration Consumption
 *
 * PHPMailer configuration is loaded exclusively from the installation-local
 * app/data/mailer.json written by the protected Site administration flow.
 * Credentials are not embedded in source code.
 *
 * Path: /app/lib/mailer.php
 */

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

class mailer
{
    /**
     * Create a PHPMailer instance from installation-local configuration.
     *
     * CMSEC-2026-4829-A
     *
     * @return \PHPMailer\PHPMailer\PHPMailer
     */
    public function create(): \PHPMailer\PHPMailer\PHPMailer
    {
        $config = $this->loadConfiguration();
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        /*
         * CMSEC-2026-4829-A
         *
         * Previous disconnected placeholder configuration is retained as a
         * disabled maintenance record:
         *
         * $mail->Host = '';
         * $mail->Username = '';
         * $mail->Password = '';
         * $mail->setFrom('', '');
         */
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = $config['smtp_auth'];
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->Port = $config['port'];
        $mail->Timeout = 20;
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        if ($config['encryption'] === 'starttls') {
            $mail->SMTPSecure =
                \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($config['encryption'] === 'smtps') {
            $mail->SMTPSecure =
                \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom(
            $config['from_email'],
            $config['from_name']
        );

        return $mail;
    }

    /**
     * Load and validate installation-local SMTP configuration.
     *
     * CMSEC-2026-4829-B
     *
     * @return array{
     *     host: string,
     *     smtp_auth: bool,
     *     username: string,
     *     password: string,
     *     encryption: string,
     *     port: int,
     *     from_email: string,
     *     from_name: string
     * }
     */
    private function loadConfiguration(): array
    {
        $applicationRoot = defined('APPROOT')
            ? APPROOT
            : dirname(__DIR__);

        $file = $applicationRoot . '/data/mailer.json';

        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException(
                'Mail delivery is not configured for this installation.'
            );
        }

        $raw = file_get_contents($file);
        $config = is_string($raw)
            ? json_decode($raw, true)
            : null;

        if (!is_array($config)) {
            throw new RuntimeException(
                'Mail configuration is invalid.'
            );
        }

        $host = trim((string) ($config['host'] ?? ''));
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        $fromEmail = trim((string) ($config['from_email'] ?? ''));
        $fromName = trim((string) ($config['from_name'] ?? ''));
        $encryption = (string) ($config['encryption'] ?? 'starttls');
        $port = filter_var(
            $config['port'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 65535,
                ],
            ]
        );
        $smtpAuth = $config['smtp_auth'] ?? null;

        if (
            $host === ''
            || (
                filter_var($host, FILTER_VALIDATE_IP) === false
                && filter_var(
                    $host,
                    FILTER_VALIDATE_DOMAIN,
                    FILTER_FLAG_HOSTNAME
                ) === false
            )
        ) {
            throw new RuntimeException('SMTP host is missing or invalid.');
        }

        if ($port === false) {
            throw new RuntimeException('SMTP port is invalid.');
        }

        if (!is_bool($smtpAuth)) {
            throw new RuntimeException('SMTP authentication setting is invalid.');
        }

        if (!in_array($encryption, ['', 'starttls', 'smtps'], true)) {
            throw new RuntimeException('SMTP encryption setting is invalid.');
        }

        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('SMTP sender address is missing or invalid.');
        }

        if (
            preg_match('/[\r\n]/', $fromName)
            || preg_match('/[\r\n]/', $username)
        ) {
            throw new RuntimeException('Mail configuration contains invalid control characters.');
        }

        if (
            $smtpAuth
            && ($username === '' || $password === '')
        ) {
            throw new RuntimeException(
                'SMTP credentials are incomplete.'
            );
        }

        return [
            'host' => $host,
            'smtp_auth' => $smtpAuth,
            'username' => $smtpAuth ? $username : '',
            'password' => $smtpAuth ? $password : '',
            'encryption' => $encryption,
            'port' => (int) $port,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
        ];
    }
}
