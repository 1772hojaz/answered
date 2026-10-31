<?php
/**
 * One-time installation script.
 *
 * 1. Verifies the database connection from config/config.php
 * 2. Creates the tables from schema.sql (if they are missing)
 * 3. Creates the first administrator account
 *
 * Delete this file after installation.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

// Setup is disabled until the operator supplies a private, random token.
// Authenticate before probing the database or revealing installation details.
$installToken = (string) config('app.install_token', '');
if (strlen($installToken) < 32) {
    http_response_code(403);
    exit('Installation is disabled. Configure a private app.install_token of at least 32 characters to enable setup.');
}
if (!isset($_SESSION['install_authorized'])
    || !hash_equals(hash('sha256', $installToken), (string) $_SESSION['install_authorized'])) {
    $providedToken = $_POST['install_token'] ?? '';
    if (!is_string($providedToken) || !hash_equals($installToken, $providedToken)) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            http_response_code(403);
        }
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Authorize setup</title>';
        echo '<form method="post">' . csrf_field();
        echo '<label>Installation token <input type="password" name="install_token" required autocomplete="off"></label>';
        echo '<button type="submit">Authorize setup</button></form></html>';
        exit;
    }
    verify_csrf();
    session_regenerate_id(true);
    $_SESSION['install_authorized'] = hash('sha256', $installToken);
    header('Location: ' . asset('install.php'), true, 303);
    exit;
}
verify_csrf();

$appName = (string) config('app.name', 'RevisionHub');
$configReady = (string) config('db.name', 'YOUR_DB_NAME') !== 'YOUR_DB_NAME';

/** @return array{ok: bool, error?: string} */
function install_test_database(): array
{
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        (string) config('db.host'),
        (string) config('db.name'),
        (string) config('db.charset', 'utf8mb4')
    );
    try {
        new PDO($dsn, (string) config('db.user'), (string) config('db.pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (PDOException $e) {
        error_log('Installer database connection failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not connect to the database. Check the server configuration.'];
    }
    return ['ok' => true];
}

/** @return array{ok: bool, error?: string} */
function install_create_tables(): array
{
    $schemaFile = APP_ROOT . '/schema.sql';
    if (!is_file($schemaFile)) {
        return ['ok' => false, 'error' => 'schema.sql was not found next to install.php.'];
    }

    $sql = (string) file_get_contents($schemaFile);
    $lines = [];
    foreach (explode("\n", $sql) as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $lines[] = $line;
    }
    $statements = array_filter(array_map('trim', explode(';', implode("\n", $lines))));

    try {
        foreach ($statements as $statement) {
            db()->exec($statement);
        }
    } catch (PDOException $e) {
        error_log('Installer schema creation failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not create the tables. Check the server logs.'];
    }

    return ['ok' => true];
}

function install_tables_exist(): bool
{
    try {
        $count = db()->query('SHOW TABLES LIKE "subjects"')->rowCount();
        return $count > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function install_admin_exists(): bool
{
    if (!install_tables_exist()) {
        return false;
    }
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
}

$step = 'check';
$error = '';
$success = '';

$checked = $configReady ? install_test_database() : ['ok' => false, 'error' => 'Edit config/config.php and enter your database name, user and password first.'];
$tablesReady = $checked['ok'] && install_tables_exist();
$adminExists = $tablesReady && install_admin_exists();

if ($adminExists) {
    unset($_SESSION['install_authorized']);
    http_response_code(403);
    exit('Installation is complete. Delete install.php and clear app.install_token.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_tables') {
        $result = install_create_tables();
        if ($result['ok']) {
            $success = 'Database tables created.';
            $tablesReady = true;
        } else {
            $error = (string) $result['error'];
        }
        $step = $tablesReady ? 'admin' : 'check';
    }

    if ($action === 'create_admin') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (!install_tables_exist()) {
            $error = 'The database tables do not exist yet.';
            $step = 'check';
        } elseif (install_admin_exists()) {
            $error = 'An administrator already exists. Delete install.php now.';
            $step = 'done';
        } elseif (!preg_match('/^[A-Za-z0-9_.\-]{3,60}$/', $username)) {
            $error = 'The username must be 3–60 characters using letters, numbers, dots, dashes or underscores.';
            $step = 'admin';
        } elseif (strlen($password) < 8) {
            $error = 'The password must be at least 8 characters long.';
            $step = 'admin';
        } elseif ($password !== $confirm) {
            $error = 'The passwords do not match.';
            $step = 'admin';
        } else {
            admin_create($username, $password);
            $success = 'Administrator account created. You can now log in.';
            $step = 'done';
        }
    }
}

if ($adminExists && $step === 'check') {
    $step = 'done';
    $success = 'This installation already has an administrator.';
} elseif ($tablesReady && $step === 'check' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $step = 'admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Install · <?= htmlspecialchars($appName) ?></title>
<link rel="stylesheet" href="<?= htmlspecialchars(asset('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(asset('assets/css/admin.css')) ?>">
<style>
  .install-wrap { max-width: 560px; margin: 0 auto; padding: 40px 18px 60px; }
  .install-steps { counter-reset: step; list-style: none; padding: 0; margin: 0 0 22px; }
  .install-steps li { counter-increment: step; position: relative; padding: 6px 0 6px 40px; color: var(--muted); }
  .install-steps li::before {
    content: counter(step); position: absolute; left: 0; top: 6px;
    width: 26px; height: 26px; border-radius: 50%; background: var(--brand-soft);
    color: var(--brand-dark); font-weight: 700; font-size: 14px;
    display: grid; place-items: center;
  }
  .install-steps li.is-current { color: var(--ink); font-weight: 700; }
  .install-steps li.is-done::before { content: "\2713"; background: #e9f7ef; color: #14653a; }
</style>
</head>
<body>
<div class="install-wrap">
  <h1><?= htmlspecialchars($appName) ?> — Setup</h1>

  <?php if ($error !== ''): ?>
    <div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success !== ''): ?>
    <div class="flash flash-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <ol class="install-steps">
    <li class="<?= $checked['ok'] ? 'is-done' : ($step === 'check' ? 'is-current' : '') ?>">Database connection</li>
    <li class="<?= $tablesReady ? 'is-done' : ($step === 'admin' ? 'is-current' : '') ?>">Database tables</li>
    <li class="<?= $step === 'done' ? 'is-done' : ($step === 'admin' ? 'is-current' : '') ?>">Administrator account</li>
  </ol>

  <?php if ($step === 'check'): ?>
    <?php if (!$checked['ok']): ?>
      <div class="flash flash-error"><?= htmlspecialchars((string) $checked['error']) ?></div>
      <p>Open <code>config/config.php</code> and set your database name, user and password, then reload this page.</p>
    <?php else: ?>
      <div class="flash flash-success">Database connection OK.</div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_tables">
        <button class="btn btn-primary" type="submit">Create database tables</button>
      </form>
      <p><small>You can also import <code>schema.sql</code> through phpMyAdmin instead.</small></p>
    <?php endif; ?>

  <?php elseif ($step === 'admin'): ?>
    <form method="post" class="form-card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_admin">
      <label class="field">
        <span>Administrator username</span>
        <input type="text" name="username" required maxlength="60" autocomplete="off">
      </label>
      <label class="field">
        <span>Password (at least 8 characters)</span>
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
      </label>
      <label class="field">
        <span>Confirm password</span>
        <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
      </label>
      <button class="btn btn-primary" type="submit">Create administrator</button>
    </form>

  <?php else: ?>
    <div class="flash flash-success">
      <?= htmlspecialchars((string) $success) ?>
      <a href="<?= htmlspecialchars(admin_url('login.php')) ?>">Go to the admin login &rarr;</a>
    </div>
    <div class="flash flash-info">
      <strong>Important:</strong> delete <code>install.php</code> from the server now that installation is complete.
    </div>
  <?php endif; ?>

  <p><a href="<?= htmlspecialchars(url('/')) ?>">&larr; Back to the site</a></p>
</div>
</body>
</html>
