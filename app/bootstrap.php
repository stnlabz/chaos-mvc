<?php

/**
 * Bootstrap
 * Pre loads Core
 */

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-26 UTC] */

// Root
$ROOT = dirname(__DIR__);

// Paths
define('LOG_PATH', $ROOT . '/logs');
define('APPROOT', $ROOT . '/app');
define('USERROOT', $ROOT . '/user');
define('PUBROOT', $ROOT . '/public');

$SITE = [
    'name' => 'Chaos MVC',
    'copyright_name' => 'Chaos MVC',
    'author' => 'Chaos MVC',
    'description' => 'Lightweight Model View Controller',
    'keywords' => '',
    'active_theme' => ''
];

$siteConfigFile = APPROOT . '/data/site.json';

if (is_file($siteConfigFile)) {
    $siteConfigRaw = file_get_contents($siteConfigFile);

    $siteConfig = $siteConfigRaw !== false
        ? json_decode($siteConfigRaw, true)
        : null;

    if (is_array($siteConfig)) {
        $SITE = array_replace(
            $SITE,
            $siteConfig
        );
    }
}

$GLOBALS['SITE'] = $SITE;

// URL detection
$scheme = (
    !empty($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off'
)
    ? 'https'
    : 'http';

$host = (string) (
    $_SERVER['SERVER_NAME']
    ?? 'localhost'
);

if (
    !preg_match(
        '/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[0-9a-f:]+\])$/i',
        $host
    )
) {
    $host = 'localhost';
}

$port = (int) (
    $_SERVER['SERVER_PORT']
    ?? 0
);

$portSuffix = (
    $port > 0
    && !(
        ($scheme === 'https' && $port === 443)
        || ($scheme === 'http' && $port === 80)
    )
)
    ? ':' . $port
    : '';

define(
    'URLROOT',
    $scheme . '://' . $host . $portSuffix
);

/**
 * Debug
 */
$debug = filter_var(
    getenv('APP_DEBUG') ?: 'false',
    FILTER_VALIDATE_BOOL
);

if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    ini_set(
        'error_log',
        LOG_PATH . '/site_errors'
    );

    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set(
        'error_log',
        LOG_PATH . '/site_errors'
    );

    error_reporting(E_ALL);
}

/**
 * Register Core error handling before configuration and routing execute.
 */
require_once APPROOT . '/core/error_handler.php';
error_handler::register();

/**
 * Config
 */
require_once APPROOT . '/core/config.php';

/**
 * Autoload
 *
 * Core-owned classes are autoloaded from /app.
 *
 * User-land module classes are intentionally not globally
 * autoloaded here. They are loaded only after the router
 * resolves a request into /user/modules/{module}.
 */
spl_autoload_register(
    function ($class) {
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
    }
);

/**
 * Sentinel MVC
 *
 * Optional perimeter-security integration. When the Sentinel module is
 * installed, inspect the request before normal MVC initialization continues.
 */
$sentinelController = USERROOT
    . '/modules/sentinel/controllers/sentinel.php';

if (
    is_file($sentinelController)
    && !is_link($sentinelController)
) {
    require_once $sentinelController;

    if (is_callable(['sentinel', 'inspect'])) {
        sentinel::inspect();
    }
}
/* End Sentinel */

/* -------------------------------------------------
   INSTALL CHECK
-------------------------------------------------- */

$installLock = LOG_PATH . '/install.lock';

if (!file_exists($installLock)) {
    try {
        $mysqli = new mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );

        if ($mysqli->connect_errno) {
            throw new Exception(
                'Database connection failed'
            );
        }

        $mysqli->close();
    } catch (Exception $e) {
        require_once APPROOT
            . '/controllers/install.php';

        (new install())->index();
        exit;
    }
}

/* -------------------------------------------------
   MAINTENANCE MODE
-------------------------------------------------- */

$maintenanceFile = APPROOT
    . '/data/updater/maintenance.lock';

if (is_file($maintenanceFile)) {
    $requestPath = (string) parse_url(
        $_SERVER['REQUEST_URI'] ?? '/',
        PHP_URL_PATH
    );

    $allowedMaintenanceRoutes = [
        '/admin',
        '/updater',
        '/login',
        '/auth/login',
        '/logout',
        '/auth/logout'
    ];

    $maintenanceAllowed = false;

    foreach (
        $allowedMaintenanceRoutes as $allowedRoute
    ) {
        if (
            $requestPath === $allowedRoute
            || str_starts_with(
                $requestPath,
                $allowedRoute . '/'
            )
        ) {
            $maintenanceAllowed = true;
            break;
        }
    }

    if (!$maintenanceAllowed) {
        http_response_code(503);

        header('Retry-After: 60');
        header(
            'Cache-Control: no-store, no-cache, must-revalidate'
        );

        require APPROOT
            . '/views/errors/maintenance.php';

        exit;
    }
}

/**
 * Traffic
 * Comes with the Chaos MVC.
 * Tracks traffic to your domain.
 */
$trafficEngine = new traffic();
$trafficEngine->collect();

/* [End AI:GPT-5.6 Sol] */
