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
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>?v=20261116">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>?v=20261117">
</head>
<body class="admin-login-body">
  <aside class="login-intro" aria-label="Welcome">
    <a class="gfg-logo" href="<?= e(url('/')) ?>"><span class="gfg-logo-mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($appName, 0, 1, 'UTF-8'), 'UTF-8')) ?></span><span class="gfg-logo-text"><?= e($appName) ?></span></a>
    <div><span class="eyebrow">BEHIND EVERY GOOD NOTE</span><h1>A little care.<br>A world of<br><em>understanding.</em></h1><p>Your space to create resources that make learning a little clearer.</p></div>
    <span class="login-intro-foot">Made for curious minds.</span>
  </aside>
  <main class="admin-login-card">
    <div class="admin-login-brand">
      <span class="brand-mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($appName, 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
      <span>Admin workspace</span>
    </div>
    <h2>Welcome back.</h2>
    <p class="login-description">Sign in to manage your study library.</p>

    <?php if ($error !== ''): ?>
      <div class="flash flash-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(admin_url('login.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

      <label class="field">
        <span>Username</span>
        <input type="text" name="username" value="<?= e($username) ?>" required autocomplete="username" maxlength="60" placeholder="Your username">
      </label>

      <div class="field">
        <label for="admin-password">Password</label>
        <span class="password-control"><input id="admin-password" type="password" name="password" required autocomplete="current-password" maxlength="255" placeholder="Your password"><button type="button" data-password-toggle aria-controls="admin-password" aria-pressed="false">Show</button></span>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>

    <p class="admin-login-foot"><a href="<?= e(url('/')) ?>">&larr; Back to the site</a></p>
  </main>
<script src="<?= e(asset('assets/js/admin.js')) ?>?v=20261117"></script>
</body>
</html><?php
}
