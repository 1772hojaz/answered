<?php
/**
 * Search across subjects, notes and past papers.
 */

$term = trim((string) ($_GET['q'] ?? ''));
$hasQuery = $term !== '';

$results = ['subjects' => [], 'notes' => [], 'papers' => []];
if ($hasQuery) {
    $results = search_all($term, 25);
}

$total = count($results['subjects']) + count($results['notes']) + count($results['papers']);

$GLOBALS['page_title'] = $hasQuery ? 'Search ZIMSEC Resources: ' . $term : 'Search ZIMSEC Revision Resources';
$GLOBALS['page_description'] = 'Search ZIMSEC subjects, revision notes, and past papers on Answered' . ($hasQuery ? ' for ' . $term . '.' : '. Find resources for your next study session.');
$GLOBALS['canonical_url'] = url('/search', $hasQuery ? ['q' => $term] : []);
$GLOBALS['page_noindex'] = true;
$GLOBALS['nav_active'] = 'search';
$GLOBALS['search_value'] = $term;
?>
<div class="gfg-layout">
  <div class="gfg-container">
    <?php render_left_nav('search'); ?>

    <section class="gfg-content">
      <?php breadcrumbs([['label' => 'Home', 'href' => url('/')], ['label' => 'Search']]); ?>

      <header class="gfg-page-head">
        <span class="eyebrow section-eyebrow">A LITTLE CURIOSITY GOES A LONG WAY</span>
        <h1>Find your next answer.</h1>
        <p class="section-sub">Search across ZIMSEC subjects, revision notes, and past papers.</p>
      </header>

      <form class="gfg-search-form" action="<?= e(form_action('/search')) ?>" method="get" role="search">
        <?= route_field('/search') ?>
        <label class="visually-hidden" for="search-input">Search notes, subjects and past papers</label>
        <input id="search-input" type="search" name="q" value="<?= e($term) ?>" placeholder="e.g. differentiation" autofocus autocomplete="off">
        <button type="submit" class="btn btn-primary">Search</button>
      </form>

      <?php if (!$hasQuery): ?>
        <p class="empty">Type a keyword to search notes, subjects and past papers.</p>
      <?php elseif ($total === 0): ?>
        <p class="empty">No results found for &ldquo;<?= e($term) ?>&rdquo;. Try a different keyword.</p>
      <?php else: ?>
        <p class="search-count"><?= $total ?> result<?= $total === 1 ? '' : 's' ?> for &ldquo;<?= e($term) ?>&rdquo;</p>

        <?php if ($results['subjects']): ?>
          <section class="gfg-block">
            <?php section_heading('Subjects'); ?>
            <ul class="gfg-list">
              <?php foreach ($results['subjects'] as $subject): ?>
                <li class="gfg-item">
                  <div class="gfg-item-body">
                    <a class="gfg-item-title" href="<?= e(url('/subjects/' . $subject['slug'])) ?>"><?= e($subject['name']) ?></a>
                    <div class="gfg-item-meta"><span class="chip chip-soft">Subject</span></div>
                  </div>
                  <span class="gfg-item-arrow" aria-hidden="true">&rsaquo;</span>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>

        <?php if ($results['notes']): ?>
          <section class="gfg-block">
            <?php section_heading('Notes'); ?>
            <ul class="gfg-list">
              <?php foreach ($results['notes'] as $note): ?>
                <li class="gfg-item">
                  <div class="gfg-item-body">
                    <a class="gfg-item-title" href="<?= e(url('/notes/' . $note['slug'])) ?>"><?= e($note['title']) ?></a>
                    <div class="gfg-item-meta">
                      <span class="chip chip-soft">Note</span>
                      <a class="chip" href="<?= e(url('/subjects/' . $note['subject_slug'])) ?>"><?= e($note['subject_name']) ?></a>
                    </div>
                    <p class="gfg-item-excerpt"><?= e(excerpt_from_html((string) $note['content'], 180)) ?></p>
                  </div>
                  <span class="gfg-item-arrow" aria-hidden="true">&rsaquo;</span>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>

        <?php if ($results['papers']): ?>
          <section class="gfg-block">
            <?php section_heading('Past Papers'); ?>
            <ul class="gfg-list">
              <?php foreach ($results['papers'] as $paper): ?>
                <li class="gfg-item">
                  <div class="gfg-item-body">
                    <a class="gfg-item-title" href="<?= e(url('/past-papers/' . $paper['slug'])) ?>"><?= e($paper['title']) ?></a>
                    <div class="gfg-item-meta">
                      <span class="chip chip-soft">Past Paper</span>
                      <a class="chip" href="<?= e(url('/subjects/' . $paper['subject_slug'])) ?>"><?= e($paper['subject_name']) ?></a>
                    </div>
                  </div>
                  <span class="gfg-item-arrow" aria-hidden="true">&rsaquo;</span>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php render_right_rail(); ?>
  </div>
</div>
