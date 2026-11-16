<?php
/**
 * Manage notes: list, filter, publish/unpublish, delete.
 */

require __DIR__ . '/../includes/secure.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_full_admin();
    $action = (string) ($_POST['action'] ?? ''); $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete') { trash_resource('notes',$id); flash_set('success','Note moved to trash.'); }
    elseif ($action === 'toggle') {
        db()->beginTransaction();
        try {
            $row=q_row('SELECT * FROM notes WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$id]);
            if ($row) {
                note_snapshot($row,'Before status change');
                $published=$row['status']!=='published';
                q('UPDATE notes SET status=?,published_at=?,version=version+1,updated_at=NOW() WHERE id=?',[$published?'published':'draft',$published?date('Y-m-d H:i:s'):null,$id]);
                workspace_audit($published?'publish':'unpublish','notes',$id,$row['title']);
            }
            db()->commit(); flash_set('success','Note status updated.');
        } catch (Throwable $failure) { db()->rollBack(); throw $failure; }
    }
    admin_redirect('notes/index.php');
}
$subjects=subjects_list();
$subjectFilter=(int)($_GET['subject_id'] ?? 0);
$statusFilter=(string)($_GET['status'] ?? '');
if (!in_array($statusFilter,['draft','published'],true)) { $statusFilter=''; }
$term=trim((string)($_GET['q'] ?? '')); $sort=(string)($_GET['sort'] ?? 'recent'); $levelFilter=(string)($_GET['level'] ?? '');
$sorts=['recent'=>'n.updated_at DESC,n.id DESC','oldest'=>'n.updated_at ASC,n.id ASC','title'=>'n.title ASC,n.id ASC'];
if (!isset($sorts[$sort])) { $sort='recent'; }
$from=' FROM notes n JOIN subjects s ON s.id=n.subject_id LEFT JOIN topics t ON t.id=n.topic_id';
$where=' WHERE n.deleted_at IS NULL'; $params=[];
if (!full_admin()) { $where.=" AND n.author_id=? AND n.status='draft'"; $params[]=current_admin()['id']; }
if ($subjectFilter>0) { $where.=' AND n.subject_id=?'; $params[]=$subjectFilter; }
if ($statusFilter!=='') { $where.=' AND n.status=?'; $params[]=$statusFilter; }
if (in_array($levelFilter,['O Level','A Level'],true)) { $where.=' AND n.level=?'; $params[]=$levelFilter; }
if ($term!=='') { $where.=' AND (n.title LIKE ? OR s.name LIKE ? OR n.syllabus_code LIKE ?)'; $params=array_merge($params,array_fill(0,3,'%'.$term.'%')); }
$total=(int)q_one('SELECT COUNT(*)'.$from.$where,$params);
$page=min(max(1,(int)($_GET['page'] ?? 1)),max(1,(int)ceil($total/25)));
$notes=q_all('SELECT n.*,s.name AS subject_name,t.name AS topic_name'.$from.$where.' ORDER BY '.$sorts[$sort].' LIMIT 25 OFFSET '.(($page-1)*25),$params);

$GLOBALS['admin_actions'] = '<a class="btn btn-primary btn-sm" href="' . e(admin_url('notes/edit.php')) . '">Create note</a>';

admin_header('Notes', 'notes');
?>

<form class="filter-bar" method="get">
  <label class="field field-inline"><span>Search notes</span><input type="search" name="q" value="<?= e($term) ?>" placeholder="Title, subject, or syllabus"></label>
  <label class="field field-inline"><span>Level</span><select name="level"><option value="">All levels</option><?php foreach (['O Level','A Level'] as $level): ?><option <?= $levelFilter===$level?'selected':'' ?>><?= e($level) ?></option><?php endforeach; ?></select></label>
  <label class="field field-inline"><span>Sort</span><select name="sort"><?php foreach (['recent'=>'Recently updated','oldest'=>'Oldest updated','title'=>'Title A–Z'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $sort===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
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
  <button class="btn btn-sm" type="submit">Apply filters</button>
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
            <?php if (full_admin()): ?>
            <form method="post" class="inline-form" data-confirm="<?= $note['status'] === 'published' ? 'Unpublish this note?' : 'Publish this note?' ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
              <button type="submit" class="btn btn-sm"><?= $note['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button>
            </form>
            <form method="post" class="inline-form" data-confirm="Move this note to trash?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Move to trash</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php admin_pagination($total,$page); admin_footer();
