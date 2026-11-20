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

require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/ads.php';

function render_layout(string $body): void
{
    $appName = (string) config('app.name', 'RevisionHub');
    $title = !empty($GLOBALS['seo_title_override']) ? $GLOBALS['seo_title_override'] : ($GLOBALS['page_title'] ?? $appName);
    if (stripos($title, 'ZIMSEC') === false) { $title .= ' - ZIMSEC'; }
    $descriptionSource = !empty($GLOBALS['seo_description_override']) ? $GLOBALS['seo_description_override'] : ($GLOBALS['page_description'] ?? 'Explore ZIMSEC revision notes and past papers by subject. Study key topics and practise exam questions with Answered.');
    if (stripos($descriptionSource, 'ZIMSEC') === false) { $descriptionSource = 'ZIMSEC revision: ' . $descriptionSource; }
    $description = seo_description($descriptionSource);
    $fullTitle = $title . ($title === $appName ? '' : ' | ' . $appName);
    $canonical = seo_absolute_url($GLOBALS['canonical_url'] ?? url('/' . current_path()));
    $robots = seo_robots();
    $active = $GLOBALS['nav_active'] ?? '';
    $bodyClass = (string) ($GLOBALS['body_class'] ?? '');
    $flashes = flash_take();
    $searchValue = (string) ($GLOBALS['search_value'] ?? '');
    $activeSubject = (string) ($GLOBALS['subject_slug_active'] ?? '');
    $hasAds = public_ads_enabled();

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
    header('X-Robots-Tag: ' . $robots);
    ?><!DOCTYPE html>
<html lang="en-ZW">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="robots" content="<?= e($robots) ?>">
<?php if (http_response_code() < 400): ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:locale" content="en_ZW">
<meta property="og:type" content="<?= in_array('Article', (array) ($GLOBALS['seo_resource']['@type'] ?? []), true) ? 'article' : 'website' ?>">
<meta property="og:site_name" content="<?= e($appName) ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= e($fullTitle) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">
<?php if (strpos($robots, 'noindex') === false): ?>
<script type="application/ld+json"><?= seo_json(seo_structured_data($fullTitle, $description, $canonical)) ?></script>
<?php endif; ?>
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>?v=20261120-resources">
<noscript><style>.gfg-nav-toggle { display: none; } .gfg-actions { display: flex; }</style></noscript>
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
        <input id="gfg-search-input" type="search" name="q" value="<?= e($searchValue) ?>" placeholder="Search notes, subjects, past papers…" maxlength="200" autocomplete="off">
        <button type="submit" aria-label="Search">
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>
        </button>
      </form>

      <nav id="gfg-primary" class="gfg-actions" aria-label="Primary" data-site-nav>
        <a href="<?= e(url('/')) ?>" class="<?= $isHome ? 'is-active' : '' ?>"<?= $isHome ? ' aria-current="page"' : '' ?>>Home</a>
        <a href="<?= e(url('/subjects')) ?>" class="<?= $isSubjects ? 'is-active' : '' ?>"<?= $isSubjects ? ' aria-current="page"' : '' ?>>Subjects</a>
        <a href="<?= e(url('/past-papers')) ?>" class="<?= $isPapers ? 'is-active' : '' ?>"<?= $isPapers ? ' aria-current="page"' : '' ?>>Past Papers</a>
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
<?php if ($hasAds): ?><div class="gfg-container ad-band"><?php render_ad_placement('leaderboard'); render_ad_placement('mobile'); ?></div><?php endif; ?>
<?= $body ?>
<?php if ($hasAds && !$isHome): ?><div class="gfg-container ad-band ad-band--native"><?php render_ad_placement('native'); ?></div><?php endif; ?>
</main>

<footer class="gfg-footer">
  <div class="gfg-container gfg-footer-grid">
    <div class="gfg-footer-brand">
      <a class="gfg-logo" href="<?= e(url('/')) ?>">
        <span class="gfg-logo-mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($appName, 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
        <span class="gfg-logo-text"><?= e($appName) ?></span>
      </a>
      <p>ZIMSEC revision notes and past papers, organised by subject and ready on any device. An independent study platform for students in Zimbabwe.</p>
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
<script src="<?= e(asset('assets/js/app.js')) ?>?v=20261120-contents"></script>
<?php if ($hasAds): ?>
<script src="<?= e(asset('assets/js/ads.js')) ?>?v=20261117"></script>
<?php endif; ?>
</body>
</html><?php
}
