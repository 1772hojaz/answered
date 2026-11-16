<?php
/**
 * Manage subjects: list, create, edit, delete.
 */

require __DIR__ . '/../includes/secure.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $subject = subject_by_id($id);

        if ($subject === null) {
            flash_set('error', 'Subject not found.');
        } else {
            $noteCount = (int) q_one('SELECT COUNT(*) FROM notes WHERE subject_id = ?', [$id]);
            $paperCount = (int) q_one('SELECT COUNT(*) FROM past_papers WHERE subject_id = ?', [$id]);

            if ($noteCount > 0 || $paperCount > 0) {
                flash_set('error', sprintf(
                    'Cannot delete "%s" while it still has %d note(s) and %d past paper(s), including trash. Reassign them or permanently delete the trashed resources first.',
                    $subject['name'],
                    $noteCount,
                    $paperCount
                ));
            } else {
                $icon = $subject['icon_path'] ?? null;
                q('DELETE FROM subjects WHERE id = ?', [$id]);
                if ($icon) {
                    $imageRow = q_row('SELECT id FROM images WHERE path = ?', [$icon]);
                    if ($imageRow !== null) {
                        delete_image((int) $imageRow['id']);
                    }
                }
                workspace_audit('delete','subjects',$id,'Deleted subjects record #' . $id);
                flash_set('success', 'Subject deleted.');
            }
        }
    }

    admin_redirect('subjects/index.php');
}

$subjects = q_all(
    'SELECT s.*,
            (SELECT COUNT(*) FROM notes n WHERE n.subject_id = s.id) AS note_count,
            (SELECT COUNT(*) FROM past_papers p WHERE p.subject_id = s.id) AS paper_count
     FROM subjects s
     ORDER BY s.sort_order, s.name'
);

$GLOBALS['admin_actions'] = '<a class="btn btn-primary btn-sm" href="' . e(admin_url('subjects/edit.php')) . '">Create subject</a>';

admin_header('Subjects', 'subjects');
?>

<?php if (!$subjects): ?>
  <p class="empty">No subjects yet. Create your first subject to get started.</p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>Subject</th>
        <th>Slug</th>
        <th>Notes</th>
        <th>Past Papers</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($subjects as $subject): ?>
        <tr>
          <td>
            <div class="cell-with-icon">
              <?php if (!empty($subject['icon_path'])): ?>
                <img class="cell-icon" src="<?= e($subject['icon_path']) ?>" alt="">
              <?php endif; ?>
              <strong><?= e($subject['name']) ?></strong>
            </div>
          </td>
          <td class="cell-muted"><?= e($subject['slug']) ?></td>
          <td><?= (int) $subject['note_count'] ?></td>
          <td><?= (int) $subject['paper_count'] ?></td>
          <td class="table-actions">
            <a class="btn btn-sm" href="<?= e(url('/subjects/' . $subject['slug'])) ?>" target="_blank" rel="noopener">View</a>
            <a class="btn btn-sm" href="<?= e(admin_url('subjects/edit.php?id=' . (int) $subject['id'])) ?>">Edit</a>
            <form method="post" class="inline-form" data-confirm="Delete this subject? This cannot be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $subject['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php admin_footer();
