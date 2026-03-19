<?php
/**
 * Bootstrap
 * Pre loads Core
 */

declare(strict_types=1);

// Root
$ROOT = dirname(__DIR__);

// Paths
define('LOG_PATH', $ROOT . '/logs');
define('APPROOT', $ROOT . '/app');
define('PUBROOT', $ROOT . '/public');

// URL detection
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('URLROOT', $scheme . '://' . $_SERVER['HTTP_HOST']);


/**
 * Debug
 */
$debug = true;

if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/site_errors');
    error_reporting(E_ALL);
}


/**
 * Config
 */
require_once APPROOT . '/core/config.php';


/**
 * Autoload
 */
spl_autoload_register(function ($class) {

    $paths = [
        APPROOT . '/core/' . $class . '.php',
        APPROOT . '/controllers/' . $class . '.php',
        APPROOT . '/models/' . $class . '.php',
        APPROOT . '/lib/' . $class . '.php',
    ];

    foreach ($paths as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});


/* -------------------------------------------------
   INSTALL CHECK
-------------------------------------------------- */

$installLock = LOG_PATH . '/install.lock';

if (!file_exists($installLock)) {
    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($mysqli->connect_errno) {
            throw new Exception('Database connection failed');
        }
    } catch (Exception $e) {
        require_once APPROOT . '/controllers/install.php';
        (new install())->index();
        exit;
    }
}

/**
 * Sentinel
 * For Chaos MVC Only
*/
$sentinel_inspect = new sentinel();
$sentinel_inspect->inspect();

/**
 * Traffic
 * Comes with the Chaos MVC.
 * Tracks traffic to your domain
*/
$trafficEngine = new traffic();
$trafficEngine->collect();

