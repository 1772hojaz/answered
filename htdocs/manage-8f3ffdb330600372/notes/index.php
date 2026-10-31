<?php
/**
 * Manage notes: list, filter, publish/unpublish, delete.
 */

require __DIR__ . '/../includes/secure.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $note = q_row('SELECT id, title FROM notes WHERE id = ?', [$id]);
        if ($note === null) {
            flash_set('error', 'Note not found.');
        } else {
            // Unlink this note from any image rows that referenced it.
            q('UPDATE images SET note_id = NULL WHERE note_id = ?', [$id]);
            q('DELETE FROM notes WHERE id = ?', [$id]);
            flash_set('success', 'Note deleted: ' . $note['title']);
        }
    } elseif ($action === 'toggle') {
        $note = q_row('SELECT id, status FROM notes WHERE id = ?', [$id]);
        if ($note === null) {
            flash_set('error', 'Note not found.');
        } elseif ($note['status'] === 'published') {
            q("UPDATE notes SET status = 'draft', published_at = NULL WHERE id = ?", [$id]);
            flash_set('success', 'Note unpublished (moved to drafts).');
        } else {
            q("UPDATE notes SET status = 'published', published_at = NOW() WHERE id = ?", [$id]);
            flash_set('success', 'Note published.');
        }
    }

    admin_redirect('notes/index.php');
}

$subjects = subjects_list();
$subjectFilter = (int) ($_GET['subject_id'] ?? 0);
$statusFilter = (string) ($_GET['status'] ?? '');
if (!in_array($statusFilter, ['draft', 'published'], true)) {
    $statusFilter = '';
}

$sql = 'SELECT n.id, n.title, n.slug, n.status, n.updated_at, n.published_at,
               s.name AS subject_name, t.name AS topic_name
        FROM notes n
        JOIN subjects s ON s.id = n.subject_id
        LEFT JOIN topics t ON t.id = n.topic_id
        WHERE 1 = 1';
$params = [];
if ($subjectFilter > 0) {
    $sql .= ' AND n.subject_id = ?';
    $params[] = $subjectFilter;
}
if ($statusFilter !== '') {
    $sql .= ' AND n.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY n.updated_at DESC LIMIT 300';

$notes = q_all($sql, $params);

$GLOBALS['admin_actions'] = '<a class="btn btn-primary btn-sm" href="' . e(admin_url('notes/edit.php')) . '">Create note</a>';

admin_header('Notes', 'notes');
?>

<form class="filter-bar" method="get">
  <label class="field field-inline">
    <span>Subject</span>
    <select name="subject_id" onchange="this.form.submit()">
      <option value="0">All subjects</option>
      <?php foreach ($subjects as $subject): ?>
        <option value="<?= (int) $subject['id'] ?>" <?= $subjectFilter === (int) $subject['id'] ? 'selected' : '' ?>>
          <?= e($subject['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="field field-inline">
    <span>Status</span>
    <select name="status" onchange="this.form.submit()">
      <option value="">All</option>
      <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Published</option>
      <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Drafts</option>
    </select>
  </label>
  <noscript><button class="btn btn-sm" type="submit">Filter</button></noscript>
</form>

<?php if (!$notes): ?>
  <p class="empty">No notes found. Create your first note to get started.</p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>Title</th>
        <th>Subject</th>
        <th>Topic</th>
        <th>Status</th>
        <th>Updated</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($notes as $note): ?>
        <tr>
          <td><strong><?= e($note['title']) ?></strong></td>
          <td><?= e($note['subject_name']) ?></td>
          <td class="cell-muted"><?= e($note['topic_name'] ?? '—') ?></td>
          <td><span class="pill pill-<?= $note['status'] === 'published' ? 'published' : 'draft' ?>"><?= e(ucfirst($note['status'])) ?></span></td>
          <td class="cell-muted"><?= e(format_date($note['updated_at'])) ?></td>
          <td class="table-actions">
            <?php if ($note['status'] === 'published'): ?>
              <a class="btn btn-sm" href="<?= e(url('/notes/' . $note['slug'])) ?>" target="_blank" rel="noopener">View</a>
            <?php endif; ?>
            <form method="post" class="inline-form" action="<?= e(admin_url('notes/preview.php')) ?>" target="_blank">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
              <button type="submit" class="btn btn-sm">Preview</button>
            </form>
            <a class="btn btn-sm" href="<?= e(admin_url('notes/edit.php?id=' . (int) $note['id'])) ?>">Edit</a>
            <form method="post" class="inline-form" data-confirm="<?= $note['status'] === 'published' ? 'Unpublish this note?' : 'Publish this note?' ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
              <button type="submit" class="btn btn-sm"><?= $note['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button>
            </form>
            <form method="post" class="inline-form" data-confirm="Delete this note permanently?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php admin_footer();
