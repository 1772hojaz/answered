<?php
/**
 * All past papers, newest years first.
 */

$page = query_int('page');
$subjects = subjects_list();
$years = array_column(paper_years(), 'year');
$subjectId = query_int('subject', 0, 0);
if (!in_array($subjectId, array_map('intval', array_column($subjects, 'id')), true)) { $subjectId = 0; }
$year = query_string('year');
if (!in_array($year, array_map('strval', $years), true)) { $year = ''; }
$level = query_string('level');
if (!in_array($level, ['O Level', 'A Level'], true)) { $level = ''; }
$filters = array_filter(['subject' => $subjectId, 'year' => $year, 'level' => $level]);
list($papers, $total) = all_papers_paginated($page, 20, $filters);
$pages = max(1, (int) ceil($total / 20));
if ($page > $pages) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

$pageSuffix = $page > 1 ? ' - Page ' . $page : '';
$GLOBALS['page_title'] = 'ZIMSEC Past Papers by Subject' . $pageSuffix;
$GLOBALS['page_description'] = 'Browse ZIMSEC past papers by subject on Answered. Open available PDFs and practise exam questions as part of your revision.' . ($page > 1 ? ' Page ' . $page . '.' : '');
$GLOBALS['canonical_url'] = url('/past-papers', $filters + ($page > 1 ? ['page' => $page] : []));
if ($filters) { $GLOBALS['page_noindex'] = true; }
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

      <form class="paper-filters" action="<?= e(form_action('/past-papers')) ?>" method="get" aria-label="Filter past papers">
        <?= route_field('/past-papers') ?>
        <label>Subject<select name="subject"><option value="">All subjects</option><?php foreach ($subjects as $item): ?><option value="<?= (int) $item['id'] ?>"<?= $subjectId === (int) $item['id'] ? ' selected' : '' ?>><?= e($item['name']) ?></option><?php endforeach; ?></select></label>
        <label>Year<select name="year"><option value="">All years</option><?php foreach ($years as $item): ?><option value="<?= e($item) ?>"<?= $year === (string) $item ? ' selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
        <label>Study level<select name="level"><option value="">All levels</option><?php foreach (['O Level', 'A Level'] as $item): ?><option value="<?= e($item) ?>"<?= $level === $item ? ' selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-primary" type="submit">Apply filters</button>
        <?php if ($filters): ?><a class="btn" href="<?= e(url('/past-papers')) ?>">Clear filters</a><?php endif; ?>
      </form>

      <?php if (!$papers && $filters): ?>
        <p class="empty">No papers match these filters. Try another subject, year, or study level.</p>
      <?php else: ?>
        <?php render_paper_list($papers); ?>
      <?php endif; ?>

      <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
          <?php if ($page > 1): ?>
            <a class="btn btn-sm" rel="prev" href="<?= e(url('/past-papers', $filters + ['page' => $page - 1])) ?>">&larr; Previous</a>
          <?php endif; ?>
          <span class="pagination-status">Page <?= $page ?> of <?= $pages ?></span>
          <?php if ($page < $pages): ?>
            <a class="btn btn-sm" rel="next" href="<?= e(url('/past-papers', $filters + ['page' => $page + 1])) ?>">Next &rarr;</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </section>

    <?php render_right_rail(); ?>
  </div>
</div>
