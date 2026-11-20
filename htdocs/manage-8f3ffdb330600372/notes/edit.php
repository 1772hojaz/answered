<?php
/**
 * Create / edit a note with the rich-text editor.
 *
 * The same form handles three buttons:
 *   - Preview   (posts to a new tab, nothing is saved)
 *   - Save draft
 *   - Publish
 */

require __DIR__ . '/../includes/secure.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$note = $id > 0 ? note_by_id($id) : null;

if ($id > 0 && $note === null) {
    flash_set('error', 'Note not found.');
    admin_redirect('notes/index.php');
}

if ($note && !can_edit_note($note)) { staff_forbidden('This note is not editable by your account.'); }
$draftKey = $id > 0 ? 'note-' . $id : (string) ($_POST['draft_key'] ?? $_GET['draft'] ?? '');
if (!preg_match('/^(?:note-[0-9]+|[a-f0-9]{32})$/D', $draftKey)) { $draftKey = bin2hex(random_bytes(16)); }
if ($id === 0 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !isset($_GET['draft'])) { admin_redirect('notes/edit.php?draft=' . $draftKey); }
$baseVersion = (int) ($_POST['version'] ?? $note['version'] ?? 1);
$metadata = array_merge(['level'=>'','syllabus_code'=>'','seo_title'=>'','seo_description'=>''], $note ?? []);
$subjects = subjects_list();
if (!$subjects) {
    flash_set('error', 'Create a subject before writing notes.');
    admin_redirect('subjects/edit.php');
}

$allTopics = q_all(
    'SELECT t.id, t.name, t.subject_id, s.name AS subject_name
     FROM topics t JOIN subjects s ON s.id = t.subject_id
     ORDER BY s.name, t.sort_order, t.name'
);

$title = (string) ($note['title'] ?? '');
$content = (string) ($note['content'] ?? '');
$subjectId = (int) ($note['subject_id'] ?? $subjects[0]['id']);
$topicId = (int) ($note['topic_id'] ?? 0);
$status = (string) ($note['status'] ?? 'draft');
$error = '';

/* ---------------------------------------------------------- preview ---- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['preview'])) {
    $previewTitle = trim((string) ($_POST['title'] ?? ''));
    $previewSubjectId = (int) ($_POST['subject_id'] ?? 0);
    $previewTopicId = (int) ($_POST['topic_id'] ?? 0);
    $previewContent = sanitize_note_html((string) ($_POST['content'] ?? ''));
    $previewSubject = subject_by_id($previewSubjectId);
    $previewTopic = $previewTopicId > 0 ? topic_by_id($previewTopicId) : null;

    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Preview · <?= e($previewTitle !== '' ? $previewTitle : 'Untitled note') ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<style>
  .preview-bar { background: #fff3cd; border-bottom: 1px solid #ffe08a; padding: 10px 18px;
    font-size: 14.5px; display: flex; gap: 14px; align-items: center; justify-content: center; flex-wrap: wrap; }
</style>
</head>
<body>
  <div class="preview-bar">
    <strong>Preview only</strong> — this is how the note will look to students. Nothing has been saved yet.
    <a href="<?= e(admin_url('notes/edit.php' . ($id > 0 ? '?id=' . $id : ''))) ?>">Back to the editor</a>
  </div>
  <div class="container section">
    <article class="note">
      <header class="note-header">
        <p class="note-eyebrow">
          <span class="badge"><?= e($previewSubject['name'] ?? 'No subject') ?></span>
          <?php if ($previewTopic): ?>
            <span class="badge badge-soft"><?= e($previewTopic['name']) ?></span>
          <?php endif; ?>
        </p>
        <h1><?= e($previewTitle !== '' ? $previewTitle : 'Untitled note') ?></h1>
        <p class="note-dates"><span>Preview of unpublished content</span></p>
      </header>
      <div class="note-content">
        <?= $previewContent !== '' ? $previewContent : '<p class="empty">This note has no content yet.</p>' ?>
      </div>
    </article>
  </div>
</body>
</html><?php
    exit;
}

/* ------------------------------------------------------------- save ---- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !isset($_POST['preview'])) {
    $action = (string) ($_POST['form_action'] ?? '');

    if ($action === 'delete') {
        trash_resource('notes', $id);
        flash_set('success', 'Note moved to trash.');
        admin_redirect('notes/index.php');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $topicId = (int) ($_POST['topic_id'] ?? 0);
    $content = (string) ($_POST['content'] ?? '');
    $publish = isset($_POST['publish']);
    if ($publish) { require_full_admin(); }
    try { $metadata = resource_metadata($_POST); } catch (InvalidArgumentException $invalid) { $error = $invalid->getMessage(); }
    $status = $publish ? 'published' : 'draft';

    if ($title === '') {
        $error = 'Please enter a title for the note.';
    } elseif (mb_strlen($title, 'UTF-8') > 200) {
        $error = 'The title must be 200 characters or fewer.';
    } elseif (subject_by_id($subjectId) === null) {
        $error = 'Please choose a subject.';
    } elseif (trim(strip_tags($content)) === '' && strpos($content, '<img') === false) {
        $error = 'Please write some content for the note.';
    }

    if ($error === '' && $topicId > 0) {
        $topic = topic_by_id($topicId);
        if ($topic === null || (int) $topic['subject_id'] !== $subjectId) {
            $topicId = 0;
        }
    }

    if ($error === '') {
        $safeContent = sanitize_note_html($content);
        db()->beginTransaction();
        try {
            $locked = $id > 0 ? q_row('SELECT * FROM notes WHERE id = ? FOR UPDATE', [$id]) : null;
            if ($id > 0 && (!$locked || !can_edit_note($locked) || (int) $locked['version'] !== $baseVersion)) {
                throw new RuntimeException('This note changed since you opened it. Your edits are preserved in this form and autosave. Reload the note before applying them again.');
            }
            if ($locked) { note_snapshot($locked, 'Before edit'); }
            $values = [$subjectId, $topicId > 0 ? $topicId : null, $title, $safeContent, $status,
                $publish ? ($locked['published_at'] ?? date('Y-m-d H:i:s')) : null,
                $metadata['level'], $metadata['syllabus_code'], $metadata['seo_title'], $metadata['seo_description']];
            if ($locked) {
                q('UPDATE notes SET subject_id=?, topic_id=?, title=?, content=?, status=?, published_at=?, level=?, syllabus_code=?, seo_title=?, seo_description=?, version=version+1, updated_at=NOW() WHERE id=?', array_merge($values,[$id]));
            } else {
                $slug = unique_slug('notes', 'slug', slugify($title));
                q('INSERT INTO notes (subject_id,topic_id,title,content,status,published_at,level,syllabus_code,seo_title,seo_description,author_id,slug) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($values,[current_admin()['id'],$slug]));
                $id = (int) db()->lastInsertId();
            }
            note_snapshot(q_row('SELECT * FROM notes WHERE id=?',[$id]), $publish ? 'Published' : 'Saved draft');
            q('DELETE FROM note_autosaves WHERE user_id=? AND draft_key=?',[current_admin()['id'],$draftKey]);
            workspace_audit($publish ? 'publish' : 'save_draft','notes',$id,$title);
            db()->commit();
            flash_set('success', $publish ? 'Note published.' : 'Draft saved.');
            admin_redirect('notes/edit.php?id=' . $id);
        } catch (PDOException $failure) {
            db()->rollBack(); error_log($failure->getMessage()); $error = 'The note could not be saved. Your edits are still in this form. Try again.';
        } catch (RuntimeException $conflict) {
            db()->rollBack(); $error = $conflict->getMessage();
        }
    }
}

$topicsForSelect = [];
foreach ($allTopics as $topic) {
    $topicsForSelect[] = [
        'id' => (int) $topic['id'],
        'name' => (string) $topic['name'],
        'subject_id' => (int) $topic['subject_id'],
        'subject_name' => (string) $topic['subject_name'],
    ];
}

$GLOBALS['admin_actions'] = $note
    ? '<a class="btn btn-sm" href="' . e(admin_url('notes/index.php')) . '">All notes</a>'
    : '<a class="btn btn-sm" href="' . e(admin_url('notes/index.php')) . '">All notes</a>';

admin_header($note ? 'Edit Note' : 'Create Note', 'notes');
?>

<?php if ($error !== ''): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<?php $recovery = q_row('SELECT saved_at FROM note_autosaves WHERE user_id=? AND draft_key=?',[current_admin()['id'],$draftKey]); ?>
<?php if ($recovery): ?><div class="flash flash-info" data-recovery-banner>A recovered draft is available from <?= e($recovery['saved_at']) ?>. <button type="button" class="btn btn-sm" data-recover-note>Load recovered draft</button><button type="button" class="btn btn-sm" data-discard-recovery>Discard recovery</button></div><?php endif; ?>
<form class="form-card note-form" method="post" id="note-form" data-autosave-url="<?= e(admin_url('actions/autosave.php')) ?>">
  <input type="hidden" name="draft_key" value="<?= e($draftKey) ?>">
  <input type="hidden" name="version" value="<?= $baseVersion ?>">
  <p class="autosave-status" data-autosave-status role="status">Changes are saved to recovery automatically. Use Save draft or Publish to apply them.</p>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <?php if ($note): ?>
    <input type="hidden" name="form_action" value="update">
  <?php endif; ?>

  <div class="form-grid">
    <label class="field">
      <span>Title *</span>
      <input type="text" name="title" id="note-title" value="<?= e($title) ?>" required maxlength="200" placeholder="e.g. Differentiation">
    </label>

    <label class="field">
      <span>Subject *</span>
      <select name="subject_id" id="note-subject" required>
        <?php foreach ($subjects as $subject): ?>
          <option value="<?= (int) $subject['id'] ?>" <?= $subjectId === (int) $subject['id'] ? 'selected' : '' ?>>
            <?= e($subject['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="field">
      <span>Topic / category</span>
      <select name="topic_id" id="note-topic">
        <option value="0">— No topic —</option>
        <?php foreach ($topicsForSelect as $topic): ?>
          <?php if ($topic['subject_id'] !== $subjectId) { continue; } ?>
          <option value="<?= $topic['id'] ?>" data-subject="<?= $topic['subject_id'] ?>" <?= $topicId === $topic['id'] ? 'selected' : '' ?>>
            <?= e($topic['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small>Only topics from the selected subject are offered.</small>
    </label>

    <div class="field">
      <span>Status</span>
      <p class="status-line">
        <span class="pill pill-<?= $status === 'published' ? 'published' : 'draft' ?>">
          <?= e(ucfirst($status)) ?>
        </span>
        <?php if ($note && $note['published_at']): ?>
          <small>Published <?= e(format_date($note['published_at'])) ?></small>
        <?php elseif ($note): ?>
          <small>Not published yet</small>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <?php resource_metadata_fields($metadata); ?>

  <div class="field editor-field">
    <span>Content *</span>
    <div class="editor" data-editor>
      <div class="editor-toolbar" role="toolbar" aria-label="Formatting">
        <select class="editor-select" data-editor-format title="Text style">
          <option value="p">Paragraph</option>
          <option value="h1">Heading 1</option>
          <option value="h2">Heading 2</option>
          <option value="h3">Heading 3</option>
          <option value="blockquote">Quote</option>
          <option value="pre">Code block</option>
        </select>
        <span class="editor-sep"></span>
        <button type="button" class="editor-btn" data-cmd="bold" title="Bold"><strong>B</strong></button>
        <button type="button" class="editor-btn" data-cmd="italic" title="Italic"><em>I</em></button>
        <button type="button" class="editor-btn" data-cmd="underline" title="Underline"><u>U</u></button>
        <button type="button" class="editor-btn" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
        <span class="editor-sep"></span>
        <button type="button" class="editor-btn" data-cmd="insertUnorderedList" title="Bulleted list">&bull; List</button>
        <button type="button" class="editor-btn" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
        <span class="editor-sep"></span>
        <button type="button" class="editor-btn" data-action="link" title="Insert link">Link</button>
        <button type="button" class="editor-btn" data-action="table" title="Insert table">Table</button>
        <button type="button" class="editor-btn" data-action="rule" title="Horizontal line">&mdash;</button>
        <button type="button" class="editor-btn" data-action="image" title="Insert image">Image</button>
        <span class="editor-sep"></span>
        <button type="button" class="editor-btn" data-cmd="removeFormat" title="Clear formatting">Clear</button>
        <button type="button" class="editor-btn" data-cmd="undo" title="Undo">&#8630;</button>
        <button type="button" class="editor-btn" data-cmd="redo" title="Redo">&#8631;</button>
      </div>
      <div class="editor-area" contenteditable="true" role="textbox" aria-multiline="true"
           aria-label="Note content" data-editor-area><?= sanitize_note_html($content) ?></div>
      <textarea name="content" id="note-content" class="editor-source" aria-hidden="true"><?= e($content) ?></textarea>
      <p class="editor-status" data-editor-status aria-live="polite"></p>
    </div>
  </div>

  <div class="form-actions editor-actions">
    <?php if (full_admin()): ?><button type="submit" name="publish" value="1" class="btn btn-primary">Publish</button><?php endif; ?>
    <button type="submit" name="save_draft" value="1" class="btn">Save draft</button>
    <button type="submit" name="preview" value="1" class="btn" formtarget="_blank">Preview</button>
    <?php if ($note && $note['status'] === 'published'): ?>
      <a class="btn" href="<?= e(url('/notes/' . $note['slug'])) ?>" target="_blank" rel="noopener">View live</a>
    <?php endif; ?>
    <?php if ($note): ?><a class="btn" href="<?= e(admin_url('notes/history.php?id=' . $id)) ?>">Revision history</a><?php endif; ?>
    <?php if ($note && full_admin()): ?>
      <button type="submit" name="form_action" value="delete" class="btn btn-danger btn-right"
              data-confirm="Move this note to trash? It will be hidden from students.">Move to trash</button>
    <?php endif; ?>
  </div>
</form>

<script>
window.REVISIONHUB = {
  csrf: <?= json_encode(csrf_token()) ?>,
  imageApi: <?= json_encode(admin_url('actions/save-image.php')) ?>,
  topics: <?= json_encode($topicsForSelect) ?>
};
</script>
<script src="<?= e(asset('assets/js/editor.js')) ?>?v=20261120"></script>
<script src="<?= e(asset('assets/js/autosave.js')) ?>?v=20261120"></script>

<?php admin_footer();
