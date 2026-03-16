<?php
/**
 * Bootstrap
 * Pre loads Core
*/

declare(strict_types=1);

// Definitions
$ROOT = dirname(__DIR__);
define('LOG_PATH', $ROOT . '/logs');
// Define path constants
define('APPROOT', $ROOT . '/app');
define('PUBROOT', $ROOT . '/public');
define('URLROOT', 'https://' . $_SERVER['HTTP_HOST']);
/**
 * Debug
 * Always use during development
 * set $debug = true; to turn it on
 * or false to turn it off
*/
$debug = true;
if($debug) {
    // Error Logging
    ini_set('display_errors', 1); // Show the user
    ini_set('log_errors', 1);     // Enable logging
    ini_set('error_log', LOG_PATH . '/site_errors');

    // Optional: set error reporting level
    error_reporting(E_ALL);
}


/**
 * Autoload
 * Loads up all core classes for usage
 * Eliminates need for include or require
*/
spl_autoload_register(function ($class) {

    $core = __DIR__ . '/core/' . $class . '.php';
    if (is_file($core)) {
        require_once $core;
        return;
    }
    
    $controllers = __DIR__ . '/controllers/' . $class . '.php';
    if (is_file($controllers)) {
        require_once $controllers;
        return;
    }
    
    $lib = __DIR__ . '/lib/' . $class . '.php';
    if (is_file($lib)) {
        require_once $lib;
        return;
    }
});

// Sentinel
$sentinel = __DIR__ . '/controllers/sentinel.php';
if(file_exists($sentinel)) {
 require_once $sentinel;
 sentinel::inspect();
}
