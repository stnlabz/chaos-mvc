<?php

$secureSession = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureSession,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

require_once dirname(__DIR__) . '/app/bootstrap.php';

new router();
