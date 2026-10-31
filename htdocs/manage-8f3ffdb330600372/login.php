<?php
/**
 * Administrator login.
 */

require __DIR__ . '/includes/boot.php';

if (is_admin()) {
    redirect_to_admin('index.php');
}

$error = '';
$username = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        login_throttle_check($username);
        if (admin_login($username, $password)) {
            redirect_to_admin(safe_admin_redirect((string) ($_POST['redirect'] ?? '')));
        }
        $error = 'Incorrect username or password.';
    }
}

$redirectTarget = safe_admin_redirect((string) ($_GET['redirect'] ?? ''));
admin_login_page($error, $username, $redirectTarget);

/**
 * Where may we send the user after login? Only internal admin paths.
 */
function safe_admin_redirect(string $target): string
{
    $base = admin_base_url() . '/';
    if ($target === ''
        || strpos($target, "\r") !== false
        || strpos($target, "\n") !== false
        || strpos($target, '//') !== false
        || strpos($target, $base) !== 0) {
        return 'index.php';
    }
    return substr($target, strlen($base));
}

function redirect_to_admin(string $path): void
{
    header('Location: ' . admin_url($path));
    exit;
}

function admin_login_page(string $error, string $username, string $redirect): void
{
    $appName = (string) config('app.name', 'RevisionHub');
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login · <?= e($appName) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin-login-body">
  <main class="admin-login-card">
    <div class="admin-login-brand">
      <span class="brand-mark" aria-hidden="true">R</span>
      <span><?= e($appName) ?> Admin</span>
    </div>

    <?php if ($error !== ''): ?>
      <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(admin_url('login.php')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

      <label class="field">
        <span>Username</span>
        <input type="text" name="username" value="<?= e($username) ?>" required autofocus maxlength="60">
      </label>

      <label class="field">
        <span>Password</span>
        <input type="password" name="password" required maxlength="255">
      </label>

      <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>

    <p class="admin-login-foot"><a href="<?= e(url('/')) ?>">&larr; Back to the site</a></p>
  </main>
</body>
</html><?php
}
