<?php
/**
 * Admin area chrome: top bar, navigation and page footer.
 */

declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * @param string $active one of dashboard|subjects|topics|notes|papers|images
 */
function admin_header(string $title, string $active = ''): void
{
    $appName = (string) config('app.name', 'RevisionHub');
    $flashes = flash_take();
    $user = current_admin();

    $navItems = [
        'dashboard' => ['Dashboard', 'index.php'],
        'subjects'  => ['Subjects', 'subjects/index.php'],
        'topics'    => ['Topics', 'topics/index.php'],
        'notes'     => ['Notes', 'notes/index.php'],
        'papers'    => ['Past Papers', 'past-papers/index.php'],
        'images'    => ['Images', 'images/index.php'],
    ];

    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e($appName) ?> Admin</title>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin-body">
<header class="admin-topbar">
  <div class="admin-topbar-inner">
    <a class="brand" href="<?= e(admin_url('index.php')) ?>">
      <span class="brand-mark" aria-hidden="true">R</span>
      <span class="brand-name"><?= e($appName) ?> <span class="brand-suffix">Admin</span></span>
    </a>
    <div class="admin-topbar-actions">
      <a class="admin-view-site" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View site</a>
      <form action="<?= e(admin_url('logout.php')) ?>" method="post" class="admin-logout-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm">Log out</button>
      </form>
    </div>
  </div>
  <nav class="admin-nav" aria-label="Admin navigation">
    <?php foreach ($navItems as $key => $item): ?>
      <a href="<?= e(admin_url($item[1])) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><?= e($item[0]) ?></a>
    <?php endforeach; ?>
  </nav>
</header>

<div class="admin-container">
  <?php if ($user): ?>
    <p class="admin-signed-in">Signed in as <strong><?= e($user['username']) ?></strong></p>
  <?php endif; ?>

  <?php if ($flashes): ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="admin-page">
    <div class="admin-page-head">
      <h1><?= e($title) ?></h1>
      <div class="admin-page-actions"><?= $GLOBALS['admin_actions'] ?? '' ?></div>
    </div>
<?php
}

function admin_footer(): void
{
    ?>
  </div>
</div>
<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html><?php
}
