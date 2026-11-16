<?php
/**
 * Create or edit a topic.
 */

require __DIR__ . '/../includes/secure.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$topic = $id > 0 ? topic_by_id($id) : null;

if ($id > 0 && $topic === null) {
    flash_set('error', 'Topic not found.');
    admin_redirect('topics/index.php');
}

$subjects = subjects_list();
if (!$subjects) {
    flash_set('error', 'Create a subject before adding topics.');
    admin_redirect('subjects/edit.php');
}

$name = (string) ($topic['name'] ?? '');
$subjectId = (int) ($topic['subject_id'] ?? (int) ($_GET['subject_id'] ?? $subjects[0]['id']));
$sortOrder = (int) ($topic['sort_order'] ?? 0);
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $sortOrder = max(0, min(9999, (int) ($_POST['sort_order'] ?? 0)));

    if ($name === '') {
        $error = 'Please enter a topic name.';
    } elseif (mb_strlen($name, 'UTF-8') > 120) {
        $error = 'The topic name must be 120 characters or fewer.';
    } elseif (subject_by_id($subjectId) === null) {
        $error = 'Please choose a valid subject.';
    }

    if ($error === '') {
        if ($topic === null) {
            $slug = unique_slug('topics', 'slug', slugify($name), null);
            // Topics only need to be unique inside their subject, so make
            // sure this slug is free for this subject specifically.
            $taken = (int) q_one('SELECT COUNT(*) FROM topics WHERE subject_id = ? AND slug = ?', [$subjectId, $slug]);
            if ($taken > 0) {
                $slug = $slug . '-' . ($taken + 1);
            }
            q('INSERT INTO topics (subject_id, name, slug, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$subjectId, $name, $slug, $sortOrder]);
            workspace_audit('create','topics',(int)db()->lastInsertId(),$name);
            flash_set('success', 'Topic created.');
        } else {
            q('UPDATE topics SET subject_id = ?, name = ?, sort_order = ? WHERE id = ?',
                [$subjectId, $name, $sortOrder, (int) $topic['id']]);
            workspace_audit('update','topics',$id,$name);
            flash_set('success', 'Topic updated.');
        }
        admin_redirect('topics/index.php');
    }
}

admin_header($topic ? 'Edit Topic' : 'Create Topic', 'topics');
?>

<?php if ($error !== ''): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<form class="form-card" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <label class="field">
    <span>Subject *</span>
    <select name="subject_id" required>
      <?php foreach ($subjects as $subject): ?>
        <option value="<?= (int) $subject['id'] ?>" <?= $subjectId === (int) $subject['id'] ? 'selected' : '' ?>>
          <?= e($subject['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>

  <label class="field">
    <span>Topic name *</span>
    <input type="text" name="name" value="<?= e($name) ?>" required maxlength="120" placeholder="e.g. Algebra">
  </label>

  <label class="field">
    <span>Sort order</span>
    <input type="number" name="sort_order" value="<?= $sortOrder ?>" min="0" max="9999">
    <small>Lower numbers appear first inside the subject.</small>
  </label>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= $topic ? 'Save changes' : 'Create topic' ?></button>
    <a class="btn" href="<?= e(admin_url('topics/index.php')) ?>">Cancel</a>
  </div>
</form>

<?php admin_footer();
