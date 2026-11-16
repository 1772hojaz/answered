<?php
require __DIR__ . '/includes/secure.php'; require_full_admin();
$type = ($_GET['type'] ?? 'notes') === 'papers' ? 'papers' : 'notes';
$table = $type === 'papers' ? 'past_papers' : 'notes';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $id=(int)($_POST['id'] ?? 0); $action=(string)($_POST['action'] ?? '');
    db()->beginTransaction();
    try {
        $row=q_row('SELECT * FROM '.$table.' WHERE id=? AND deleted_at IS NOT NULL FOR UPDATE',[$id]);
        if (!$row) { throw new RuntimeException('This item is no longer in the trash.'); }
        if ($action==='restore') {
            q('UPDATE '.$table.' SET deleted_at=NULL,updated_at=NOW()'.($table==='notes' ? ",status='draft',published_at=NULL,version=version+1" : '').' WHERE id=?',[$id]);
            workspace_audit('restore_trash',$table,$id,'Restored: '.$row['title']);
        } elseif ($action==='purge' && ($_POST['confirmation'] ?? '')==='DELETE') {
            if ($table==='notes') { q('UPDATE images SET note_id=NULL WHERE note_id=?',[$id]); }
            workspace_audit('permanent_delete',$table,$id,'Permanently deleted: '.$row['title']);
            q('DELETE FROM '.$table.' WHERE id=?',[$id]);
        } else { throw new RuntimeException('Type DELETE to confirm permanent deletion.'); }
        db()->commit(); flash_set('success',$action==='restore' ? ($table==='notes' ? 'Note restored as a draft.' : 'Past paper restored.') : 'Item permanently deleted.'); admin_redirect('trash.php?type='.$type);
    } catch (RuntimeException $failure) { db()->rollBack(); $error=$failure instanceof PDOException ? 'Could not update this item.' : $failure->getMessage(); }
}
$total=(int)q_one('SELECT COUNT(*) FROM '.$table.' WHERE deleted_at IS NOT NULL');
$page=min(max(1,(int)($_GET['page'] ?? 1)),max(1,(int)ceil($total/25)));
$rows=q_all('SELECT id,title,deleted_at FROM '.$table.' WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC,id DESC LIMIT 25 OFFSET '.(($page-1)*25));
admin_header('Trash','trash'); ?>
<nav class="gfg-tabs"><a href="?type=notes" class="<?= $type==='notes'?'is-active':'' ?>">Notes</a><a href="?type=papers" class="<?= $type==='papers'?'is-active':'' ?>">Past papers</a></nav>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<p class="field-note">Trashed items are hidden from students and search engines. Notes return as drafts; restoring a paper makes it available again.</p>
<?php if (!$rows): ?><p class="empty">The trash is empty.</p><?php endif; ?>
<?php foreach ($rows as $row): ?><section class="panel"><h2><?= e($row['title']) ?></h2><p class="field-note">Moved to trash <?= e($row['deleted_at']) ?></p>
<form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn" name="action" value="restore">Restore<?= $type==='notes'?' as draft':'' ?></button></form>
<details class="purge-details"><summary>Permanently delete</summary><form method="post" data-confirm="Permanently delete this item? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><label class="field"><span>Type DELETE to confirm</span><input name="confirmation" required pattern="DELETE" autocomplete="off"></label><button class="btn btn-danger" name="action" value="purge">Delete permanently</button></form></details>
</section><?php endforeach; admin_pagination($total,$page); admin_footer();
