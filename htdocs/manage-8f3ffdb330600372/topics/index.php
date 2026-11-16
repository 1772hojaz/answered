<?php
/**
 * Manage topics: list, create, edit, delete.
 */

require __DIR__ . '/../includes/secure.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $topic = topic_by_id($id);

        if ($topic === null) {
            flash_set('error', 'Topic not found.');
        } else {
            $noteCount = (int) q_one('SELECT COUNT(*) FROM notes WHERE topic_id = ?', [$id]);
            if ($noteCount > 0) {
                flash_set('error', sprintf(
                    'Cannot delete "%s" while %d note(s) still use it. Reassign those notes first.',
                    $topic['name'],
                    $noteCount
                ));
            } else {
                q('DELETE FROM topics WHERE id = ?', [$id]);
                workspace_audit('delete','topics',$id,'Deleted topics record #' . $id);
                flash_set('success', 'Topic deleted.');
            }
        }
    }

    admin_redirect('topics/index.php');
}

$subjects = subjects_list();
$subjectFilter = (int) ($_GET['subject_id'] ?? 0);

$sql = 'SELECT t.*, s.name AS subject_name, s.slug AS subject_slug,
               (SELECT COUNT(*) FROM notes n WHERE n.topic_id = t.id) AS note_count
        FROM topics t
        JOIN subjects s ON s.id = t.subject_id';
$params = [];
if ($subjectFilter > 0) {
    $sql .= ' WHERE t.subject_id = ?';
    $params[] = $subjectFilter;
}
$sql .= ' ORDER BY s.name, t.sort_order, t.name';

$topics = q_all($sql, $params);

$GLOBALS['admin_actions'] = '<a class="btn btn-primary btn-sm" href="' . e(admin_url('topics/edit.php')) . '">Create topic</a>';

admin_header('Topics', 'topics');
?>

<form class="filter-bar" method="get">
  <label class="field field-inline">
    <span>Filter by subject</span>
    <select name="subject_id" onchange="this.form.submit()">
      <option value="0">All subjects</option>
      <?php foreach ($subjects as $subject): ?>
        <option value="<?= (int) $subject['id'] ?>" <?= $subjectFilter === (int) $subject['id'] ? 'selected' : '' ?>>
          <?= e($subject['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="btn btn-sm" type="submit">Filter</button></noscript>
</form>

<?php if (!$topics): ?>
  <p class="empty">No topics yet. Topics group notes inside a subject (optional).</p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>Topic</th>
        <th>Subject</th>
        <th>Notes</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($topics as $topic): ?>
        <tr>
          <td><strong><?= e($topic['name']) ?></strong></td>
          <td>
            <a href="<?= e(url('/subjects/' . $topic['subject_slug'])) ?>" target="_blank" rel="noopener">
              <?= e($topic['subject_name']) ?>
            </a>
          </td>
          <td><?= (int) $topic['note_count'] ?></td>
          <td class="table-actions">
            <a class="btn btn-sm" href="<?= e(admin_url('topics/edit.php?id=' . (int) $topic['id'])) ?>">Edit</a>
            <form method="post" class="inline-form" data-confirm="Delete this topic?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $topic['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php admin_footer();
