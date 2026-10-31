<?php
/**
 * Create or edit a subject (including optional subject icon).
 */

require __DIR__ . '/../includes/secure.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$subject = $id > 0 ? subject_by_id($id) : null;

if ($id > 0 && $subject === null) {
    flash_set('error', 'Subject not found.');
    admin_redirect('subjects/index.php');
}

$name = (string) ($subject['name'] ?? '');
$description = (string) ($subject['description'] ?? '');
$sortOrder = (int) ($subject['sort_order'] ?? 0);
$iconUrl = (string) ($subject['icon_path'] ?? '');
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $sortOrder = max(0, min(9999, (int) ($_POST['sort_order'] ?? 0)));
    $iconUrl = trim((string) ($_POST['icon_url'] ?? ''));

    if ($name === '') {
        $error = 'Please enter a subject name.';
    } elseif (mb_strlen($name, 'UTF-8') > 120) {
        $error = 'The subject name must be 120 characters or fewer.';
    }

    $iconPath = null;
    if ($error === '' && $iconUrl !== '') {
        $saved = save_image_link($iconUrl, 'subject_icon', $name . ' icon');
        if (!$saved['ok']) {
            $error = (string) $saved['error'];
        } else {
            $iconPath = (string) $saved['url'];
        }
    }

    if ($error === '') {
        $oldIcon = $subject['icon_path'] ?? null;

        if ($subject === null) {
            $slug = unique_slug('subjects', 'slug', slugify($name));
            q(
                'INSERT INTO subjects (name, slug, description, icon_path, sort_order, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [$name, $slug, $description !== '' ? $description : null, $iconPath, $sortOrder]
            );
            flash_set('success', 'Subject created.');
        } else {
            q(
                'UPDATE subjects SET name = ?, description = ?, icon_path = ?, sort_order = ? WHERE id = ?',
                [$name, $description !== '' ? $description : null, $iconPath, $sortOrder, (int) $subject['id']]
            );
            flash_set('success', 'Subject updated.');
        }

        // Drop the old image record when the icon was replaced or cleared.
        if ($oldIcon && $oldIcon !== $iconPath) {
            $row = q_row('SELECT id FROM images WHERE path = ?', [$oldIcon]);
            if ($row !== null) {
                delete_image((int) $row['id']);
            }
        }

        admin_redirect('subjects/index.php');
    }
}

$GLOBALS['admin_actions'] = $subject
    ? '<a class="btn btn-sm" href="' . e(url('/subjects/' . $subject['slug'])) . '" target="_blank" rel="noopener">View page</a>'
    : '';

admin_header($subject ? 'Edit Subject' : 'Create Subject', 'subjects');
?>

<?php if ($error !== ''): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<form class="form-card" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <label class="field">
    <span>Subject name *</span>
    <input type="text" name="name" value="<?= e($name) ?>" required maxlength="120" placeholder="e.g. Mathematics">
  </label>

  <?php if ($subject !== null): ?>
    <p class="field-note">URL: <code><?= e('/subjects/' . $subject['slug']) ?></code> (the URL does not change when you rename the subject)</p>
  <?php endif; ?>

  <label class="field">
    <span>Description</span>
    <textarea name="description" rows="3" maxlength="2000" placeholder="Shown on the subject page and home page"><?= e($description) ?></textarea>
  </label>

  <label class="field">
    <span>Sort order</span>
    <input type="number" name="sort_order" value="<?= $sortOrder ?>" min="0" max="9999">
    <small>Lower numbers appear first.</small>
  </label>

  <label class="field">
    <span>Subject icon (optional)</span>
    <?php if ($iconUrl !== ''): ?>
      <span class="field-preview"><img src="<?= e($iconUrl) ?>" alt="Current subject icon"></span>
    <?php endif; ?>
    <input type="url" name="icon_url" value="<?= e($iconUrl) ?>" maxlength="2000" placeholder="https://example.com/icon.png">
    <small>Paste a link to an image hosted elsewhere. Leave blank to remove the icon. Square images look best.</small>
  </label>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= $subject ? 'Save changes' : 'Create subject' ?></button>
    <a class="btn" href="<?= e(admin_url('subjects/index.php')) ?>">Cancel</a>
  </div>
</form>

<?php admin_footer();
