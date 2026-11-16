<?php
/**
 * All past papers, newest years first.
 */

$page = max(1, (int) ($_GET['page'] ?? 1));
list($papers, $total) = all_papers_paginated($page, 20);
$pages = max(1, (int) ceil($total / 20));
if ($page > $pages) {
    require __DIR__ . '/404.php';
    return;
}

$pageSuffix = $page > 1 ? ' - Page ' . $page : '';
$GLOBALS['page_title'] = 'ZIMSEC Past Papers by Subject' . $pageSuffix;
$GLOBALS['page_description'] = 'Browse ZIMSEC past papers by subject on Answered. Open available PDFs and practise exam questions as part of your revision.' . ($page > 1 ? ' Page ' . $page . '.' : '');
$GLOBALS['canonical_url'] = url('/past-papers', $page > 1 ? ['page' => $page] : []);
$GLOBALS['seo_page_type'] = 'CollectionPage';
$GLOBALS['nav_active'] = 'papers';
?>
<div class="gfg-layout">
  <div class="gfg-container">
    <?php render_left_nav('papers'); ?>

    <section class="gfg-content">
      <?php breadcrumbs([['label' => 'Home', 'href' => url('/')], ['label' => 'Past Papers']]); ?>

      <header class="gfg-page-head">
        <span class="eyebrow section-eyebrow">PUT LEARNING INTO PRACTICE</span>
        <h1>ZIMSEC Past Papers</h1>
        <p class="section-sub"><?= $total ?> paper<?= $total === 1 ? '' : 's' ?> to help you practise, spot patterns, and prepare for exam day.</p>
      </header>

      <?php render_paper_list($papers); ?>

      <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
          <?php if ($page > 1): ?>
            <a class="btn btn-sm" href="<?= e(url('/past-papers', ['page' => $page - 1])) ?>">&larr; Previous</a>
          <?php endif; ?>
          <span class="pagination-status">Page <?= $page ?> of <?= $pages ?></span>
          <?php if ($page < $pages): ?>
            <a class="btn btn-sm" href="<?= e(url('/past-papers', ['page' => $page + 1])) ?>">Next &rarr;</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </section>

    <?php render_right_rail(); ?>
  </div>
</div>
