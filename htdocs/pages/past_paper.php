<?php
/**
 * One past paper: metadata, in-page PDF preview and download.
 */

$paper = paper_by_slug((string) $paper_slug);

if ($paper === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

$paperUrl = public_path((string) $paper['file_path']);
$meta = [];
if (!empty($paper['year'])) {
    $meta[] = (string) $paper['year'];
}
if (!empty($paper['exam_board'])) {
    $meta[] = $paper['exam_board'];
}
if (!empty($paper['paper_number'])) {
    $meta[] = 'Paper ' . $paper['paper_number'];
}

$GLOBALS['page_title'] = (string) $paper['title'];
$GLOBALS['page_description'] = 'Past paper: ' . $paper['title'] . ($paper['subject_name'] ? ' — ' . $paper['subject_name'] : '');
$GLOBALS['nav_active'] = 'papers';
$GLOBALS['subject_slug_active'] = (string) $paper['subject_slug'];
?>
<div class="gfg-layout">
  <div class="gfg-container">
    <?php render_left_nav('papers', (string) $paper['subject_slug']); ?>

    <section class="gfg-content">
      <?php breadcrumbs([
          ['label' => 'Home', 'href' => url('/')],
          ['label' => 'Past Papers', 'href' => url('/past-papers')],
          ['label' => $paper['subject_name'], 'href' => url('/subjects/' . $paper['subject_slug'])],
          ['label' => (string) $paper['title']],
      ]); ?>

      <article class="gfg-article">
        <header class="paper-header">
          <p class="note-eyebrow">
            <a class="chip" href="<?= e(url('/subjects/' . $paper['subject_slug'])) ?>"><?= e($paper['subject_name']) ?></a>
            <?php if ($meta): ?>
              <span class="chip chip-soft"><?= e(implode(' · ', $meta)) ?></span>
            <?php endif; ?>
          </p>
          <h1><?= e($paper['title']) ?></h1>
          <p class="note-dates">
            <span>Added <?= e(format_date($paper['created_at'])) ?></span>
          </p>
          <?php if (!empty($paper['description'])): ?>
            <p class="paper-description"><?= nl2br(e($paper['description'])) ?></p>
          <?php endif; ?>
          <p class="paper-actions">
            <a class="btn btn-primary" href="<?= e($paperUrl) ?>" download>Download PDF</a>
            <a class="btn" href="<?= e($paperUrl) ?>" target="_blank" rel="noopener">Open in new tab</a>
          </p>
        </header>

        <div class="paper-viewer">
          <iframe class="paper-frame" src="<?= e($paperUrl) ?>" title="<?= e($paper['title']) ?>" loading="lazy"></iframe>
          <p class="paper-fallback">
            If the paper does not display,
            <a href="<?= e($paperUrl) ?>" target="_blank" rel="noopener">open the PDF directly</a>.
          </p>
        </div>
      </article>
    </section>

    <?php render_right_rail(); ?>
  </div>
</div>