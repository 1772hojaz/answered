<?php
/**
 * All past papers, newest years first.
 */

$page = max(1, (int) ($_GET['page'] ?? 1));
list($papers, $total) = all_papers_paginated($page, 20);
$pages = max(1, (int) ceil($total / 20));

$GLOBALS['page_title'] = 'Past Papers';
$GLOBALS['nav_active'] = 'papers';
?>
<div class="gfg-layout">
  <div class="gfg-container">
    <?php render_left_nav('papers'); ?>

    <section class="gfg-content">
      <?php breadcrumbs([['label' => 'Home', 'href' => url('/')], ['label' => 'Past Papers']]); ?>

      <header class="gfg-page-head">
        <h1>Past Papers</h1>
        <p class="section-sub"><?= $total ?> paper<?= $total === 1 ? '' : 's' ?> available</p>
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