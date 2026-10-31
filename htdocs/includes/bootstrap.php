<?php
/**
 * Application bootstrap: configuration, session, helpers.
 * Every entry point (public and admin) requires this file first.
 */

declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
} else {
    // Included twice: nothing left to do.
    return;
}

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config/config.php';

if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing configuration. Copy config/config.sample.php to config/config.php and edit it.');
}

/** @var array $APP_CONFIG */
$APP_CONFIG = require $configFile;

if (!isset($APP_CONFIG['db'], $APP_CONFIG['app'])) {
    http_response_code(500);
    exit('Incomplete configuration in config/config.php.');
}

/**
 * Read a configuration value using dot notation, e.g. config('app.name').
 */
function config(string $key, $default = null)
{
    global $APP_CONFIG;

    $value = $APP_CONFIG;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

ini_set('display_errors', config('app.debug', false) ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');

$secure = (bool) config('app.https', false)
    || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
$basePath = rtrim((string) config('app.base_path', ''), '/');
$cookiePath = $basePath === '' ? '/' : $basePath . '/';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $cookiePath,
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('revisionhub_session');
session_start();

require_once APP_ROOT . '/includes/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/sanitize.php';
require_once APP_ROOT . '/includes/uploads.php';
require_once APP_ROOT . '/includes/auth.php';
