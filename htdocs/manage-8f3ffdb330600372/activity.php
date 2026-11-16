<?php
require __DIR__ . '/includes/secure.php'; require_full_admin();
$term=trim((string)($_GET['q'] ?? '')); $like='%'.$term.'%';
$where=' WHERE actor_name LIKE ? OR action LIKE ? OR summary LIKE ?'; $params=[$like,$like,$like];
$total=(int)q_one('SELECT COUNT(*) FROM admin_activity'.$where,$params);
$page=min(max(1,(int)($_GET['page'] ?? 1)),max(1,(int)ceil($total/25)));
$rows=q_all('SELECT * FROM admin_activity'.$where.' ORDER BY id DESC LIMIT 25 OFFSET '.(($page-1)*25),$params);
admin_header('Activity history','activity'); ?>
<form class="filter-bar" method="get"><label class="field field-inline"><span>Search activity</span><input type="search" name="q" value="<?= e($term) ?>" placeholder="Account, action, or resource"></label><button class="btn">Search</button></form>
<?php if (!$rows): ?><p class="empty">No matching activity yet.</p><?php else: ?><table class="table"><thead><tr><th>When (UTC)</th><th>Who</th><th>Action</th><th>Details</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= e($row['created_at']) ?></td><td><?= e($row['actor_name']) ?></td><td><?= e(str_replace('_',' ',$row['action'])) ?></td><td><?= e($row['summary']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; admin_pagination($total,$page); admin_footer();
