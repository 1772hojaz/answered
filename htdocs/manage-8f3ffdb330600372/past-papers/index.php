<?php
/**
 * Manage past papers: list, edit metadata, replace PDF, delete.
 */

require __DIR__ . '/../includes/secure.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $paper = paper_by_id($id);
        if ($paper === null) {
            flash_set('error', 'Past paper not found.');
        } else {
            delete_paper_file($paper['file_path'] ?? null);
            q('DELETE FROM past_papers WHERE id = ?', [$id]);
            flash_set('success', 'Past paper deleted.');
        }
    }

    admin_redirect('past-papers/index.php');
}

$subjects = subjects_list();
$subjectFilter = (int) ($_GET['subject_id'] ?? 0);

$sql = 'SELECT p.*, s.name AS subject_name, s.slug AS subject_slug
        FROM past_papers p
        JOIN subjects s ON s.id = p.subject_id';
$params = [];
if ($subjectFilter > 0) {
    $sql .= ' WHERE p.subject_id = ?';
    $params[] = $subjectFilter;
}
$sql .= ' ORDER BY p.year DESC, p.paper_number, p.title LIMIT 300';

$papers = q_all($sql, $params);

$GLOBALS['admin_actions'] = '<a class="btn btn-primary btn-sm" href="' . e(admin_url('past-papers/edit.php')) . '">Add past paper</a>';

admin_header('Past Papers', 'papers');
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
  <noscript><button class="btn btn-sm" type="submit">Filter</button></noscript>
</form>

<?php if (!$papers): ?>
  <p class="empty">No past papers yet.</p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>Title</th>
        <th>Subject</th>
        <th>Year</th>
        <th>Board</th>
        <th>Paper</th>
        <th>PDF link</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($papers as $paper): ?>
        <?php $host = (string) (parse_url((string) $paper['file_path'], PHP_URL_HOST) ?: $paper['file_path']); ?>
        <tr>
          <td><strong><?= e($paper['title']) ?></strong></td>
          <td><?= e($paper['subject_name']) ?></td>
          <td><?= e((string) ($paper['year'] ?? '—')) ?></td>
          <td class="cell-muted"><?= e($paper['exam_board'] ?? '—') ?></td>
          <td class="cell-muted"><?= e($paper['paper_number'] ?? '—') ?></td>
          <td class="cell-muted">
            <a href="<?= e((string) $paper['file_path']) ?>" target="_blank" rel="noopener" title="<?= e((string) $paper['file_path']) ?>"><?= e($host) ?></a>
          </td>
          <td class="table-actions">
            <a class="btn btn-sm" href="<?= e(url('/past-papers/' . $paper['slug'])) ?>" target="_blank" rel="noopener">View</a>
            <a class="btn btn-sm" href="<?= e(admin_url('past-papers/edit.php?id=' . (int) $paper['id'])) ?>">Edit</a>
            <form method="post" class="inline-form" data-confirm="Delete this past paper?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $paper['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php admin_footer();
