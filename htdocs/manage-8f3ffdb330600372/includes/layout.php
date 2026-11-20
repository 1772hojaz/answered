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
        'trash'     => ['Trash', 'trash.php'],
        'users'     => ['Accounts', 'users.php'],
        'activity'  => ['Activity', 'activity.php'],
        'profile'   => ['My account', 'profile.php'],
    ];
    if (!full_admin()) { $navItems = array_intersect_key($navItems, array_flip(['dashboard','notes','profile'])); }
    $navIcons = [
        'dashboard' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
        'subjects' => 'M3 4h7l2 2 2-2h7v16h-7l-2 1-2-1H3zM12 6v15',
        'topics' => 'M4 5h16M4 12h16M4 19h10',
        'notes' => 'M5 3h10l4 4v14H5zM14 3v5h5M8 12h8M8 16h6',
        'papers' => 'M8 3h12v15H8zM4 7v14h12M11 7h6M11 11h6',
        'images' => 'M3 3h18v18H3zM3 17l6-6 4 4 3-3 5 5M15 7h1',
        'trash' => 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8M17 4a4 4 0 0 1 0 7M22 21v-2a4 4 0 0 0-3-4',
        'activity' => 'M3 12h4l3-8 4 16 3-8h4',
        'profile' => 'M20 21v-2a7 7 0 0 0-14 0v2M13 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8',
    ];
    $pageDescriptions = [
        'dashboard' => 'A little organisation. A better learning experience.',
        'subjects' => 'Build a clear starting point for every learner.',
        'topics' => 'Give every idea a place in your study library.',
        'notes' => 'Create, refine, and publish helpful revision notes.',
        'papers' => 'Keep exam practice organised and easy to find.',
        'images' => 'Manage the visuals that bring your notes to life.',
        'trash' => 'Recover notes and papers before they are gone for good.',
        'users' => 'Manage staff access and keep account details up to date.',
        'activity' => 'See who changed, published, or recovered a resource.',
        'profile' => 'Keep your account password up to date.',
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
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>?v=20261116">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>?v=20261117">
</head>
<body class="admin-body">
<a class="skip-link" href="#admin-main">Skip to content</a>
<header class="admin-topbar">
  <div class="admin-topbar-inner">
    <a class="brand" href="<?= e(admin_url('index.php')) ?>">
      <span class="brand-mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($appName, 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
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
  <span class="admin-nav-label">WORKSPACE</span>
  <nav class="admin-nav" aria-label="Admin navigation">
    <?php foreach ($navItems as $key => $item): ?>
      <a href="<?= e(admin_url($item[1])) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($navIcons[$key]) ?>"/></svg><span><?= e($item[0]) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <div class="admin-sidebar-note"><span class="status-dot" aria-hidden="true"></span> A space for better learning.</div>
</header>

<main id="admin-main" class="admin-container">
  <?php if ($user): ?>
    <div class="admin-workspace-bar"><span><?= full_admin() ? 'Administrator workspace' : 'Editor workspace · own drafts' ?></span><p class="admin-signed-in"><span class="admin-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['username'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span> <?= e($user['username']) ?></p></div>
  <?php endif; ?>

  <?php if ($flashes): ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="admin-page">
    <div class="admin-page-head">
      <div><span class="eyebrow section-eyebrow">YOUR PLATFORM, AT A GLANCE</span><h1><?= e($title) ?></h1><p class="admin-page-description"><?= e($pageDescriptions[$active] ?? 'Manage your learning resources.') ?></p></div>
      <div class="admin-page-actions"><?= $GLOBALS['admin_actions'] ?? '' ?></div>
    </div>
<?php
}

function admin_footer(): void
{
    ?>
  </div>
</main>
<script src="<?= e(asset('assets/js/admin.js')) ?>?v=20261120"></script>
</body>
</html><?php
}
