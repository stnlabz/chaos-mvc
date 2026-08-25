<?php

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
$secureSession = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureSession,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
/* [End AI:GPT-5.6 Sol] */

require_once dirname(__DIR__) . '/app/bootstrap.php';

new router();
