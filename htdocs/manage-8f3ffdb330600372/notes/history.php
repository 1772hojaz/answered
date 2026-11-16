<?php
require __DIR__ . '/../includes/secure.php';
$id = (int) ($_GET['id'] ?? 0);
$note = note_by_id($id);
if (!$note || !can_edit_note($note)) { staff_forbidden('This note history is not available to your account.'); }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    db()->beginTransaction();
    try {
        $locked = q_row('SELECT * FROM notes WHERE id=? FOR UPDATE',[$id]);
        if (!$locked || !can_edit_note($locked)) { throw new RuntimeException('The note is no longer editable.'); }
        if ((int) ($_POST['version'] ?? 0) !== (int) $locked['version']) { throw new RuntimeException('The note changed. Reload this page before restoring a revision.'); }
        $revision = q_row('SELECT * FROM note_revisions WHERE id=? AND note_id=?',[(int)($_POST['revision_id'] ?? 0),$id]);
        if (!$revision) { throw new RuntimeException('Revision not found.'); }
        $data = json_decode($revision['snapshot'],true,512,JSON_THROW_ON_ERROR);
        if (!subject_by_id((int)$data['subject_id'])) { throw new RuntimeException('The original subject no longer exists.'); }
        $topic = !empty($data['topic_id']) ? topic_by_id((int)$data['topic_id']) : null;
        $topicId = $topic && (int)$topic['subject_id']===(int)$data['subject_id'] ? $topic['id'] : null;
        note_snapshot($locked,'Before revision restore');
        q('UPDATE notes SET title=?,content=?,subject_id=?,topic_id=?,level=?,syllabus_code=?,seo_title=?,seo_description=?,status=\'draft\',published_at=NULL,version=version+1,updated_at=NOW() WHERE id=?',
            [$data['title'],sanitize_note_html($data['content']),$data['subject_id'],$topicId,$data['level'] ?? '',$data['syllabus_code'] ?? '',$data['seo_title'] ?? '',$data['seo_description'] ?? '',$id]);
        note_snapshot(q_row('SELECT * FROM notes WHERE id=?',[$id]),'Restored revision');
        workspace_audit('restore_revision','notes',$id,'Restored revision #' . $revision['id'] . ': ' . $data['title']);
        db()->commit(); flash_set('success','Revision restored as a draft. Review it before publishing.'); admin_redirect('notes/edit.php?id=' . $id);
    } catch (RuntimeException $failure) { db()->rollBack(); $error = $failure instanceof PDOException ? 'Could not restore this revision.' : $failure->getMessage(); }
}
$total = (int)q_one('SELECT COUNT(*) FROM note_revisions WHERE note_id=?',[$id]);
$page = min(max(1,(int)($_GET['page'] ?? 1)),max(1,(int)ceil($total/25)));
$rows = q_all('SELECT r.*,u.username FROM note_revisions r LEFT JOIN users u ON u.id=r.user_id WHERE r.note_id=? ORDER BY r.id DESC LIMIT 25 OFFSET ' . (($page-1)*25),[$id]);
$GLOBALS['admin_actions'] = '<a class="btn" href="' . e(admin_url('notes/edit.php?id='.$id)) . '">Back to editor</a>';
admin_header('Revision history','notes');
?>
<p class="field-note">History for <strong><?= e($note['title']) ?></strong>. Restoring a revision creates a draft and preserves the current version in history.</p>
<?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (!$rows): ?><p class="empty">Save this note to start its revision history.</p><?php endif; ?>
<?php foreach ($rows as $row): $snapshot=json_decode($row['snapshot'],true); ?>
<details class="panel"><summary><strong><?= e($row['created_at']) ?></strong> · <?= e($row['reason']) ?> · <?= e($row['username'] ?? 'Former account') ?></summary>
<h2><?= e($snapshot['title']) ?></h2><div class="note-content"><?= sanitize_note_html($snapshot['content']) ?></div>
<form method="post" data-confirm="Restore this version as a draft? Any published version will be unpublished."><?= csrf_field() ?><input type="hidden" name="revision_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="version" value="<?= (int)$note['version'] ?>"><button class="btn" type="submit">Restore as draft</button></form>
</details><?php endforeach; ?>
<?php admin_pagination($total,$page); admin_footer();
