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

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
// URL detection. APP_URL is recommended in production.
$configuredUrl = trim((string) getenv('APP_URL'));

if ($configuredUrl !== '') {
    $urlRoot = rtrim($configuredUrl, '/');
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https'
        : 'http';
    $host = $_SERVER['SERVER_NAME'] ?? 'localhost';

    if (!preg_match('/^[a-z0-9.-]+$/i', $host)) {
        $host = 'localhost';
    }

    $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
    $defaultPort = ($scheme === 'https') ? 443 : 80;
    $portSuffix = ($port > 0 && $port !== $defaultPort) ? ':' . $port : '';
    $urlRoot = $scheme . '://' . $host . $portSuffix;
}

define('URLROOT', $urlRoot);


/**
 * Debug
 */
$debugValue = filter_var(
    getenv('CHAOS_DEBUG') ?: 'false',
    FILTER_VALIDATE_BOOLEAN
);
$debug = $debugValue === true;

if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0750, true);
}

ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/site_errors');
error_reporting(E_ALL);

if ($debug) {
    error_log('Chaos MVC debug mode is enabled.');
}
/* [End AI:GPT-5.6 Sol] */


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
 * Traffic
 * Comes with the Chaos MVC.
 * Tracks traffic to your domain
*/
$trafficEngine = new traffic();
$trafficEngine->collect();
