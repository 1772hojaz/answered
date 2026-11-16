<?php
/**
 * A single published note, rendered as a reading page.
 */

$note = note_by_slug((string) $note_slug);

if ($note === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

$links = [
    ['label' => 'Home', 'href' => url('/')],
    ['label' => 'Subjects', 'href' => url('/subjects')],
    ['label' => $note['subject_name'], 'href' => url('/subjects/' . $note['subject_slug'])],
];
if (!empty($note['topic_name'])) {
    $links[] = ['label' => $note['topic_name']];
}
$links[] = ['label' => $note['title']];

$related = related_notes((int) $note['subject_id'], (int) $note['id'], 6);
$subjectPapers = papers_for_subject((int) $note['subject_id']);

$GLOBALS['page_title'] = $note['title'] . ' - ZIMSEC ' . $note['subject_name'] . ' Notes';
$GLOBALS['page_description'] = 'ZIMSEC ' . $note['subject_name'] . ': ' . $note['title'] . '. '
    . excerpt_from_html((string) $note['content'], 160);
$GLOBALS['canonical_url'] = url('/notes/' . $note['slug']);
$GLOBALS['seo_title_override'] = $note['seo_title'] ?? '';
$GLOBALS['seo_description_override'] = $note['seo_description'] ?? '';
$GLOBALS['seo_resource'] = ['@type' => ['Article', 'LearningResource'], 'headline' => $note['title'],
    'name' => $note['title'], 'about' => $note['subject_name'], 'learningResourceType' => 'Revision notes', 'educationalUse' => 'revision'];
foreach (['published_at' => 'datePublished', 'updated_at' => 'dateModified'] as $field => $property) {
    if (!empty($note[$field]) && ($timestamp = strtotime($note[$field])) !== false) {
        $GLOBALS['seo_resource'][$property] = date(DATE_W3C, $timestamp);
    }
}
$GLOBALS['nav_active'] = 'subjects';
$GLOBALS['subject_slug_active'] = (string) $note['subject_slug'];
?>
<div class="gfg-layout">
  <div class="gfg-container">
    <?php render_left_nav('subjects', (string) $note['subject_slug']); ?>

    <section class="gfg-content">
      <?php breadcrumbs($links); ?>

      <article class="gfg-article">
        <header class="note-header">
          <p class="note-eyebrow">
            <?php foreach (['level','syllabus_code'] as $field): if (!empty($note[$field])): ?><span class="chip chip-soft"><?= e($note[$field]) ?></span><?php endif; endforeach; ?>
            <span class="chip chip-soft">ZIMSEC revision</span>
            <a class="chip" href="<?= e(url('/subjects/' . $note['subject_slug'])) ?>"><?= e($note['subject_name']) ?></a>
            <?php if (!empty($note['topic_name'])): ?>
              <span class="chip chip-soft"><?= e($note['topic_name']) ?></span>
            <?php endif; ?>
          </p>
          <h1><?= e($note['title']) ?></h1>
          <p class="note-dates">
            <?php if (!empty($note['published_at'])): ?>
              <span>Published <?= e(format_date($note['published_at'])) ?></span>
            <?php endif; ?>
            <?php if (!empty($note['updated_at']) && $note['updated_at'] !== $note['published_at']): ?>
              <span>Last updated <?= e(format_date($note['updated_at'])) ?></span>
            <?php endif; ?>
          </p>
        </header>

        <div class="note-content">
          <?= sanitize_note_html((string) $note['content']) ?>
        </div>

        <footer class="note-footer">
          <a class="btn" href="<?= e(url('/subjects/' . $note['subject_slug'])) ?>">&larr; More <?= e($note['subject_name']) ?> notes</a>
          <a class="btn btn-primary" href="<?= e(url('/subjects/' . $note['subject_slug'] . '/past-papers')) ?>">Past papers &rarr;</a>
        </footer>
      </article>
    </section>

    <aside class="gfg-rail" aria-label="Recommended">
      <?php if ($related): ?>
      <section class="gfg-rail-card">
        <h2 class="gfg-rail-title">Recommended Reads</h2>
        <ul class="gfg-rail-list">
          <?php foreach ($related as $relatedNote): ?>
            <li>
              <a href="<?= e(url('/notes/' . $relatedNote['slug'])) ?>"><?= e($relatedNote['title']) ?></a>
              <?php if (!empty($relatedNote['topic_name'])): ?>
                <span class="gfg-rail-count"><?= e($relatedNote['topic_name']) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <?php if ($subjectPapers): ?>
      <section class="gfg-rail-card">
        <h2 class="gfg-rail-title">Past Papers</h2>
        <ul class="gfg-rail-list">
          <?php foreach (array_slice($subjectPapers, 0, 5) as $railPaper): ?>
            <li>
              <a href="<?= e(url('/past-papers/' . $railPaper['slug'])) ?>"><?= e($railPaper['title']) ?></a>
              <?php if (!empty($railPaper['year'])): ?>
                <span class="gfg-rail-count"><?= e((string) $railPaper['year']) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <section class="gfg-rail-card gfg-rail-cta">
        <h2 class="gfg-rail-title">Browse <?= e($note['subject_name']) ?></h2>
        <p>Open every note and past paper in this subject.</p>
        <a class="btn btn-primary btn-block" href="<?= e(url('/subjects/' . $note['subject_slug'])) ?>">Go to subject</a>
      </section>
    </aside>
  </div>
</div>
