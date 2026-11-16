<?php
/**
 * Administrator authentication: session-based, password_hash protected.
 */

declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

function current_admin(): ?array
{
    static $cached = false;

    if ($cached !== false) {
        return $cached;
    }

    if (empty($_SESSION['admin_id'])) {
        $cached = null;
        return null;
    }

    $user = q_row('SELECT id, username, email, role, active, auth_version, created_at FROM users WHERE id = ?', [
        (int) $_SESSION['admin_id'],
    ]);

    if ($user === null || !(int) $user['active'] || !in_array($user['role'], ['admin', 'editor'], true)
        || (int) ($_SESSION['auth_version'] ?? 0) !== (int) $user['auth_version']) {
        admin_logout();
        $cached = null;
        return null;
    }

    $cached = $user;
    return $user;
}

function is_admin(): bool
{
    return current_admin() !== null;
}

/**
 * Gate every admin page and action behind this call.
 */
function require_admin(): array
{
    $user = current_admin();
    if ($user === null) {
        $back = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . admin_url('login.php') . ($back ? '?redirect=' . rawurlencode($back) : ''));
        exit;
    }
    return $user;
}

/**
 * Reserve an attempt in shared storage before checking credentials. A new
 * session must not reset the limit. Count both account and direct peer IP;
 * forwarded headers are untrusted unless a proxy is explicitly configured.
 */
function login_throttle_check(string $username): void
{
    $dir = sys_get_temp_dir() . '/revisionhub-login-' . hash('sha256', APP_ROOT);
    if ((!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) || is_link($dir)) {
        http_response_code(503);
        exit('Login is temporarily unavailable.');
    }
    $file = $dir . '/attempts.json';
    $handle = is_link($file) ? false : @fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        http_response_code(503);
        exit('Login is temporarily unavailable.');
    }
    $raw = stream_get_contents($handle);
    $attempts = $raw === '' ? [] : json_decode((string) $raw, true);
    if (!is_array($attempts)) {
        fclose($handle);
        http_response_code(503);
        exit('Login is temporarily unavailable.');
    }
    $now = time();
    foreach ($attempts as $key => $bucket) {
        if ((int) ($bucket['until'] ?? 0) <= $now) {
            unset($attempts[$key]);
        }
    }
    $limits = [
        'user-' . hash('sha256', strtolower(trim($username))) => 5,
        'ip-' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) => 20,
    ];
    foreach ($limits as $key => $limit) {
        if ((int) ($attempts[$key]['count'] ?? 0) >= $limit) {
            $retry = max(1, (int) $attempts[$key]['until'] - $now);
            fclose($handle);
            header('Retry-After: ' . $retry);
            http_response_code(429);
            exit('Too many login attempts. Please wait a minute and try again.');
        }
    }
    foreach ($limits as $key => $limit) {
        $attempts[$key] = [
            'count' => (int) ($attempts[$key]['count'] ?? 0) + 1,
            'until' => (int) ($attempts[$key]['until'] ?? ($now + 60)),
        ];
    }
    $encoded = json_encode($attempts);
    rewind($handle);
    $saved = is_string($encoded) && ftruncate($handle, 0)
        && fwrite($handle, $encoded) === strlen($encoded) && fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    if (!$saved) {
        http_response_code(503);
        exit('Login is temporarily unavailable.');
    }
}

/**
 * Attempt to authenticate an administrator. Returns true on success.
 */
function admin_login(string $username, string $password): bool
{
    $user = q_row('SELECT id, password_hash, active, role, auth_version FROM users WHERE username = ? OR email = ?', [
        $username,
        $username,
    ]);

    if ($user === null) {
        // Burn comparable time so the response does not reveal whether the
        // account exists.
        password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
        return false;
    }

    if (!password_verify($password, (string) $user['password_hash']) || !(int) $user['active'] || !in_array($user['role'], ['admin','editor'], true)) {
        return false;
    }

    // Rehash when the cost parameters have changed.
    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [
            password_hash($password, PASSWORD_DEFAULT),
            (int) $user['id'],
        ]);
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $user['id'];
    $_SESSION['auth_version'] = (int) $user['auth_version'];
    unset($_SESSION['csrf_token']);

    return true;
}

function admin_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
}

/**
 * Create (or update) an administrator account. Used by install.php.
 */
function admin_create(string $username, string $password, string $email = ''): void
{
    $existing = q_row('SELECT id FROM users WHERE username = ?', [$username]);

    if ($existing !== null) {
        q('UPDATE users SET password_hash = ?, email = ? WHERE id = ?', [
            password_hash($password, PASSWORD_DEFAULT),
            $email,
            (int) $existing['id'],
        ]);
        return;
    }

    q('INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())', [
        $username,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
    ]);
}
