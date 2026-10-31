<?php
/**
 * Preview an existing note in a new tab (used from the notes list).
 */

require __DIR__ . '/../includes/secure.php';

$note = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $note = note_by_id((int) ($_POST['id'] ?? 0));
}

if ($note === null) {
    admin_redirect('notes/index.php');
}

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Preview · <?= e($note['title']) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<style>
  .preview-bar { background: #fff3cd; border-bottom: 1px solid #ffe08a; padding: 10px 18px;
    font-size: 14.5px; display: flex; gap: 14px; align-items: center; justify-content: center; flex-wrap: wrap; }
</style>
</head>
<body>
  <div class="preview-bar">
    <strong>Preview</strong>
    <?= $note['status'] === 'published'
        ? 'This note is published.'
        : 'This note is a draft — students cannot see it yet.' ?>
    <a href="<?= e(admin_url('notes/edit.php?id=' . (int) $note['id'])) ?>">Back to the editor</a>
  </div>

  <div class="container section">
    <article class="note">
      <header class="note-header">
        <p class="note-eyebrow">
          <a class="badge" href="<?= e(url('/subjects/' . $note['subject_slug'])) ?>"><?= e($note['subject_name']) ?></a>
          <?php if (!empty($note['topic_name'])): ?>
            <span class="badge badge-soft"><?= e($note['topic_name']) ?></span>
          <?php endif; ?>
        </p>
        <h1><?= e($note['title']) ?></h1>
        <p class="note-dates">
          <span>Updated <?= e(format_date($note['updated_at'])) ?></span>
          <?php if (!empty($note['published_at'])): ?>
            <span>Published <?= e(format_date($note['published_at'])) ?></span>
          <?php endif; ?>
        </p>
      </header>
      <div class="note-content">
        <?= sanitize_note_html((string) $note['content']) ?>
      </div>
    </article>
  </div>
</body>
</html>
