<?php
/**
 * Manage past papers: list, edit metadata, replace PDF, delete.
 */

require __DIR__ . '/../includes/secure.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (($_POST['action'] ?? '')==='delete') { trash_resource('past_papers',(int)($_POST['id'] ?? 0)); flash_set('success','Past paper moved to trash.'); }
    admin_redirect('past-papers/index.php');
}
$subjects=subjects_list(); $subjectFilter=(int)($_GET['subject_id'] ?? 0);
$term=trim((string)($_GET['q'] ?? '')); $yearFilter=(int)($_GET['year'] ?? 0); $levelFilter=(string)($_GET['level'] ?? '');
$sort=(string)($_GET['sort'] ?? 'year'); $sorts=['year'=>'p.year DESC,p.id DESC','title'=>'p.title,p.id','updated'=>'p.updated_at DESC,p.id DESC']; if (!isset($sorts[$sort])) { $sort='year'; }
$from=' FROM past_papers p JOIN subjects s ON s.id=p.subject_id'; $where=' WHERE p.deleted_at IS NULL'; $params=[];
if ($subjectFilter>0) { $where.=' AND p.subject_id=?'; $params[]=$subjectFilter; }
if ($yearFilter>0) { $where.=' AND p.year=?'; $params[]=$yearFilter; }
if (in_array($levelFilter,['O Level','A Level'],true)) { $where.=' AND p.level=?'; $params[]=$levelFilter; }
if ($term!=='') { $where.=' AND (p.title LIKE ? OR p.syllabus_code LIKE ? OR p.exam_session LIKE ? OR s.name LIKE ?)'; $params=array_merge($params,array_fill(0,4,'%'.$term.'%')); }
$total=(int)q_one('SELECT COUNT(*)'.$from.$where,$params); $page=min(max(1,(int)($_GET['page'] ?? 1)),max(1,(int)ceil($total/25)));
$papers=q_all('SELECT p.*,s.name AS subject_name,s.slug AS subject_slug'.$from.$where.' ORDER BY '.$sorts[$sort].' LIMIT 25 OFFSET '.(($page-1)*25),$params);

$GLOBALS['admin_actions'] = '<a class="btn btn-primary btn-sm" href="' . e(admin_url('past-papers/edit.php')) . '">Add past paper</a>';

admin_header('Past Papers', 'papers');
?>

<form class="filter-bar" method="get">
<label class="field field-inline"><span>Search papers</span><input type="search" name="q" value="<?= e($term) ?>" placeholder="Title, syllabus, or session"></label>
<label class="field field-inline"><span>Year</span><input type="number" name="year" value="<?= $yearFilter ?: '' ?>" min="1900" max="2100"></label>
<label class="field field-inline"><span>Level</span><select name="level"><option value="">All levels</option><?php foreach (['O Level','A Level'] as $level): ?><option <?= $levelFilter===$level?'selected':'' ?>><?= e($level) ?></option><?php endforeach; ?></select></label>
<label class="field field-inline"><span>Sort</span><select name="sort"><?php foreach (['year'=>'Newest year','title'=>'Title A–Z','updated'=>'Recently updated'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $sort===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
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
  <button class="btn btn-sm" type="submit">Apply filters</button>
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
          <td class="cell-muted"><?= e(implode(' · ',array_filter([$paper['exam_board'],$paper['level'],$paper['syllabus_code'],$paper['exam_session']]))) ?></td>
          <td class="cell-muted"><?= e($paper['paper_number'] ?? '—') ?></td>
          <td class="cell-muted">
            <a href="<?= e((string) $paper['file_path']) ?>" target="_blank" rel="noopener" title="<?= e((string) $paper['file_path']) ?>"><?= e($host) ?></a>
          </td>
          <td class="table-actions">
            <a class="btn btn-sm" href="<?= e(url('/past-papers/' . $paper['slug'])) ?>" target="_blank" rel="noopener">View</a>
            <a class="btn btn-sm" href="<?= e(admin_url('past-papers/edit.php?id=' . (int) $paper['id'])) ?>">Edit</a>
            <form method="post" class="inline-form" data-confirm="Move this past paper to trash?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $paper['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Move to trash</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php admin_pagination($total,$page); admin_footer();
