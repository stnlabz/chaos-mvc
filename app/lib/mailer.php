<?php

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

class mailer
{
    /**
     * Create and configure a PHPMailer instance with hardcoded credentials.
     */
    public function create(): \PHPMailer\PHPMailer\PHPMailer
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        // Hardcoded Configuration
        $mail->isSMTP();
        $mail->Host       = 'mail.poemei.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rictus@poemei.com';
        $mail->Password   = 'H6a91c62a!';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->isHTML(true);

        $mail->setFrom('rictus@poemei.com', 'Rictus');

        return $mail;
    }
}
