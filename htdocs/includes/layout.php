<?php
/**
 * Public page shell (GeeksforGeeks-style: top bar, category strip and
 * multi-column footer). Pages are rendered inside index.php with output
 * buffering and then wrapped by render_layout(). A page sets
 * $GLOBALS['page_title'], $GLOBALS['page_description'], $GLOBALS['nav_active']
 * and optionally $GLOBALS['body_class'] before printing its content.
 */

declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

function render_layout(string $body): void
{
    $appName = (string) config('app.name', 'RevisionHub');
    $title = $GLOBALS['page_title'] ?? $appName;
    $description = $GLOBALS['page_description'] ?? 'Free revision notes and past papers organised by subject.';
    $active = $GLOBALS['nav_active'] ?? '';
    $bodyClass = (string) ($GLOBALS['body_class'] ?? '');
    $flashes = flash_take();
    $searchValue = (string) ($GLOBALS['search_value'] ?? '');
    $activeSubject = (string) ($GLOBALS['subject_slug_active'] ?? '');
    $hasSideAds = (string) config('ads.left_zone_id', '') !== ''
        || (string) config('ads.right_zone_id', '') !== '';

    try {
        $navSubjects = subjects_with_counts();
    } catch (Throwable $e) {
        $navSubjects = [];
    }

    $isHome = $active === 'home';
    $isSubjects = $active === 'subjects';
    $isPapers = $active === 'papers';

    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<?php if ($hasSideAds): ?>
<script id="aclib" type="text/javascript" src="https://acscdn.com/script/aclib.js"></script>
<?php endif; ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?><?= $title === $appName ? '' : ' · ' . e($appName) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="gfg<?= $bodyClass !== '' ? ' ' . e($bodyClass) : '' ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="gfg-header">
  <div>
    <div class="gfg-container gfg-header-inner">
      <button class="gfg-nav-toggle" type="button" aria-expanded="false" aria-controls="gfg-primary" data-nav-toggle>
        <span class="gfg-nav-bars" aria-hidden="true"><span></span><span></span><span></span></span>
        <span class="visually-hidden">Menu</span>
      </button>

      <a class="gfg-logo" href="<?= e(url('/')) ?>">
        <span class="gfg-logo-mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($appName, 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
        <span class="gfg-logo-text"><?= e($appName) ?></span>
      </a>

      <form class="gfg-search" action="<?= e(form_action('/search')) ?>" method="get" role="search">
        <?= route_field('/search') ?>
        <label class="visually-hidden" for="gfg-search-input">Search notes and past papers</label>
        <input id="gfg-search-input" type="search" name="q" value="<?= e($searchValue) ?>" placeholder="Search notes, subjects, past papers…" autocomplete="off">
        <button type="submit" aria-label="Search">
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>
        </button>
      </form>

      <nav id="gfg-primary" class="gfg-actions" aria-label="Primary" data-site-nav>
        <a href="<?= e(url('/')) ?>" class="<?= $isHome ? 'is-active' : '' ?>">Home</a>
        <a href="<?= e(url('/subjects')) ?>" class="<?= $isSubjects ? 'is-active' : '' ?>">Subjects</a>
        <a href="<?= e(url('/past-papers')) ?>" class="<?= $isPapers ? 'is-active' : '' ?>">Past Papers</a>
      </nav>
    </div>
  </div>

  <nav class="gfg-catbar" aria-label="Subjects">
    <div class="gfg-container gfg-catbar-inner">
      <a class="gfg-catbar-all <?= $isSubjects && $activeSubject === '' ? 'is-active' : '' ?>" href="<?= e(url('/subjects')) ?>">All Subjects</a>
      <?php foreach ($navSubjects as $navSubject): ?>
        <a href="<?= e(url('/subjects/' . $navSubject['slug'])) ?>" class="<?= $activeSubject === (string) $navSubject['slug'] ? 'is-active' : '' ?>"><?= e($navSubject['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('/past-papers')) ?>" class="<?= $isPapers ? 'is-active' : '' ?>">Past Papers</a>
    </div>
  </nav>
</header>

<?php if ($flashes): ?>
<div class="gfg-container flashes">
  <?php foreach ($flashes as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<main id="main" class="gfg-main">
<?= $body ?>
</main>

<footer class="gfg-footer">
  <div class="gfg-container gfg-footer-grid">
    <div class="gfg-footer-brand">
      <a class="gfg-logo" href="<?= e(url('/')) ?>">
        <span class="gfg-logo-mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($appName, 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
        <span class="gfg-logo-text"><?= e($appName) ?></span>
      </a>
      <p>Clear revision notes and past papers, organised by subject and ready on any device.</p>
    </div>
    <div class="gfg-footer-col">
      <h4>Explore</h4>
      <ul>
        <li><a href="<?= e(url('/')) ?>">Home</a></li>
        <li><a href="<?= e(url('/subjects')) ?>">All Subjects</a></li>
        <li><a href="<?= e(url('/past-papers')) ?>">Past Papers</a></li>
        <li><a href="<?= e(url('/search')) ?>">Search</a></li>
      </ul>
    </div>
    <div class="gfg-footer-col">
      <h4>Popular Subjects</h4>
      <ul>
        <?php foreach (array_slice($navSubjects, 0, 5) as $navSubject): ?>
          <li><a href="<?= e(url('/subjects/' . $navSubject['slug'])) ?>"><?= e($navSubject['name']) ?></a></li>
        <?php endforeach; ?>
        <?php if (!$navSubjects): ?>
          <li><span>None yet</span></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="gfg-footer-bottom">
    <div class="gfg-container gfg-footer-bottom-inner">
      <p>&copy; <?= date('Y') ?> <?= e($appName) ?>. Revision materials for students.</p>
      <p>Built for focused, distraction-free study.</p>
    </div>
  </div>
</footer>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
<?php if ($hasSideAds): ?>
<script src="<?= e(asset('assets/js/side-ads.js')) ?>"></script>
<?php endif; ?>
</body>
</html><?php
}
