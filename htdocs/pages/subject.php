<?php
/**
 * Subject page: notes (grouped by topic) and past papers.
 *
 * $section may be 'all', 'notes' or 'papers' depending on the URL.
 */

$subject = subject_by_slug((string) $subject_slug);

if ($subject === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

$section = in_array($section, ['notes', 'papers'], true) ? $section : 'all';
$notes = notes_for_subject((int) $subject['id']);
$topics = topics_for_subject((int) $subject['id']);
$papers = papers_for_subject((int) $subject['id']);

// Group notes by topic, keeping topic order and putting untitled-topic
// notes under "General".
$grouped = ['' => []];
foreach ($topics as $topic) {
    $grouped[(string) $topic['id']] = [];
}
foreach ($notes as $note) {
    $key = $note['topic_id'] !== null ? (string) $note['topic_id'] : '';
    $grouped[$key][] = $note;
}
$topicNames = ['' => 'General'];
foreach ($topics as $topic) {
    $topicNames[(string) $topic['id']] = (string) $topic['name'];
}

$subjectLabel = 'ZIMSEC ' . $subject['name'];
$subjectIcon = public_media_url((string) ($subject['icon_path'] ?? ''));
$resourceLabel = $section === 'notes' ? 'Revision Notes' : ($section === 'papers' ? 'Past Papers' : 'Notes & Past Papers');
$GLOBALS['page_title'] = $subjectLabel . ' - ' . $resourceLabel;
$GLOBALS['page_description'] = 'Explore ' . $subjectLabel . ' ' . strtolower($resourceLabel) . ' on Answered. '
    . ($subject['description'] ?: 'Review key topics and practise exam questions at your own pace.');
$GLOBALS['canonical_url'] = url('/subjects/' . $subject['slug'] . ($section === 'notes' ? '/notes' : ($section === 'papers' ? '/past-papers' : '')));
$GLOBALS['seo_page_type'] = 'CollectionPage';
$GLOBALS['nav_active'] = 'subjects';
$GLOBALS['subject_slug_active'] = (string) $subject['slug'];
?>
<div class="gfg-layout">
  <div class="gfg-container">
    <?php render_left_nav('subjects', (string) $subject['slug']); ?>

    <section class="gfg-content">
      <?php breadcrumbs([
          ['label' => 'Home', 'href' => url('/')],
          ['label' => 'Subjects', 'href' => url('/subjects')],
          ['label' => (string) $subject['name']],
      ]); ?>

      <header class="gfg-page-head subject-header">
        <?php if ($subjectIcon !== null): ?>
          <img class="subject-header-icon" src="<?= e($subjectIcon) ?>" alt="" loading="lazy">
        <?php else: ?>
          <span class="subject-header-letter" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $subject['name'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
        <?php endif; ?>
        <div class="subject-header-text">
          <h1><?= e($subjectLabel) ?><?= $section === 'notes' ? ' Notes' : ($section === 'papers' ? ' Past Papers' : '') ?></h1>
          <?php if (!empty($subject['description'])): ?>
            <p><?= nl2br(e($subject['description'])) ?></p>
          <?php endif; ?>
        </div>
      </header>

      <nav class="gfg-tabs" aria-label="Subject sections">
        <a href="<?= e(url('/subjects/' . $subject['slug'])) ?>" class="<?= $section === 'all' ? 'is-active' : '' ?>">Overview</a>
        <a href="<?= e(url('/subjects/' . $subject['slug'] . '/notes')) ?>" class="<?= $section === 'notes' ? 'is-active' : '' ?>">Notes <span class="tab-count"><?= count($notes) ?></span></a>
        <a href="<?= e(url('/subjects/' . $subject['slug'] . '/past-papers')) ?>" class="<?= $section === 'papers' ? 'is-active' : '' ?>">Past Papers <span class="tab-count"><?= count($papers) ?></span></a>
      </nav>

      <?php if ($section !== 'papers'): ?>
      <section class="gfg-block" id="notes">
        <?php section_heading('Notes'); ?>
        <?php if (!$notes): ?>
          <p class="empty">No notes have been published for this subject yet.</p>
        <?php else: ?>
          <?php foreach ($grouped as $topicId => $topicNotes): ?>
            <?php if (!$topicNotes) { continue; } ?>
            <div class="topic-group">
              <h3 class="gfg-subhead"><?= e($topicNames[$topicId] ?? 'General') ?></h3>
              <?php render_note_list($topicNotes); ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if ($section !== 'notes'): ?>
      <section class="gfg-block" id="past-papers">
        <?php section_heading('Past Papers'); ?>
        <?php render_paper_list($papers); ?>
      </section>
      <?php endif; ?>
    </section>

    <?php render_right_rail(); ?>
  </div>
</div>
