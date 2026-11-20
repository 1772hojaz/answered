<?php
/**
 * Local development router — emulates the .htaccess rules for `php -S`.
 *
 * Usage: php -S localhost:8000 router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($path)) {
    http_response_code(400);
    exit;
}
$path = rawurldecode($path);

// The built-in server ignores .htaccess. Apply the same private-file rules
// here before allowing it to serve any existing file, including .well-known.
if (preg_match('/[\x00-\x1F\x7F\\\\]/', $path)
    || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $path)
    || preg_match('#(?:^|/)\.(?!well-known(?:/|$))#i', $path)
    || preg_match('#(?:^|/)(?:config|includes|keys)(?:/|$)#i', $path)
    || preg_match('/\.(?:sql|md|log|yml|yaml|json|lock|ini|bak|backup|old|orig|save|swp)$/i', $path)
    || preg_match('#^/uploads/.*\.(?:php[0-9]?|phtml|phar|pl|py|cgi|sh|shtml)(?:/|$)#i', $path)) {
    http_response_code(404);
    exit;
}

$file = realpath(__DIR__ . $path);
$root = realpath(__DIR__) . DIRECTORY_SEPARATOR;
if ($file !== false && strpos($file . DIRECTORY_SEPARATOR, $root) !== 0) {
    http_response_code(404);
    exit;
}

// Redirect Let's Encrypt HTTP-01 challenges to CertKit (.htaccess equivalent).
if (preg_match('#^/\.well-known/acme-challenge/(.+)$#i', $path, $m)) {
    header('Location: https://app.certkit.io/http-challenge/free/e12517860de1445cb4ad21f402167600/'
        . rawurlencode($m[1]), true, 302);
    exit;
}

// Serve real .well-known files untouched (pki-validation, etc).
if (strpos($path, '/.well-known/') === 0) {
    if ($file !== false && is_file($file) && !preg_match('/\.php$/i', $path)) {
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }
    http_response_code(404);
    exit;
}

// Match the public crawler endpoints used by Apache.
if ($path === '/sitemap.xml' || $path === '/robots.txt') {
    require __DIR__ . ($path === '/sitemap.xml' ? '/sitemap.php' : '/robots.php');
    exit;
}

// Clean URLs: fall through to index.php if no real file/dir matches.
if ($file !== false && $path !== '/' && is_file($file)) {
    return false;
}
if ($file !== false && is_dir($file) && strpos($path, '/manage-8f3ffdb330600372/') === 0) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
