<?php
require __DIR__ . '/../includes/secure.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
$userId = (int) current_admin()['id'];
$id = (int) ($_POST['id'] ?? 0);
$key = (string) ($_POST['draft_key'] ?? '');
if (($id > 0 && $key !== 'note-' . $id) || ($id === 0 && !preg_match('/^[a-f0-9]{32}$/D', $key))) {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Invalid draft identifier.']); exit;
}
db()->beginTransaction();
try {
    $note = $id > 0 ? q_row('SELECT * FROM notes WHERE id=? FOR UPDATE',[$id]) : null;
    if ($id > 0 && (!$note || !can_edit_note($note))) { db()->rollBack(); staff_forbidden(); }
    $action = (string) ($_POST['autosave_action'] ?? 'save');
    if ($action === 'load') {
        $row = q_row('SELECT payload,saved_at FROM note_autosaves WHERE user_id=? AND draft_key=?',[$userId,$key]);
        db()->commit(); echo json_encode(['ok'=>true,'draft'=>$row ? json_decode($row['payload'],true) : null]); exit;
    }
    if ($action === 'discard') {
        q('DELETE FROM note_autosaves WHERE user_id=? AND draft_key=?',[$userId,$key]);
        db()->commit(); echo json_encode(['ok'=>true]); exit;
    }
    if ($action !== 'save') { throw new InvalidArgumentException('Invalid recovery action.'); }
    $metadata = resource_metadata($_POST);
    $title = trim((string) ($_POST['title'] ?? ''));
    $content = (string) ($_POST['content'] ?? '');
    if (mb_strlen($title,'UTF-8') > 200 || strlen($content) > 2000000) { throw new InvalidArgumentException('This recovery draft is too large. Save it manually.'); }
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    if (!subject_by_id($subjectId)) { throw new InvalidArgumentException('Choose a subject before saving recovery.'); }
    $topicId = (int) ($_POST['topic_id'] ?? 0);
    $topic = $topicId ? topic_by_id($topicId) : null;
    $payload = array_merge($metadata,['title'=>$title,'content'=>sanitize_note_html($content),'subject_id'=>$subjectId,
        'topic_id'=>$topic && (int)$topic['subject_id']===$subjectId ? $topicId : 0,'version'=>(int)($_POST['version'] ?? 1)]);
    q('INSERT INTO note_autosaves (user_id,draft_key,note_id,payload) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload), saved_at=NOW()',
        [$userId,$key,$id ?: null,json_encode($payload,JSON_INVALID_UTF8_SUBSTITUTE)]);
    db()->commit(); echo json_encode(['ok'=>true,'saved_at'=>date('H:i:s')]);
} catch (InvalidArgumentException $invalid) {
    db()->rollBack(); http_response_code(422); echo json_encode(['ok'=>false,'error'=>$invalid->getMessage()]);
} catch (Throwable $failure) {
    if (db()->inTransaction()) { db()->rollBack(); }
    error_log($failure->getMessage()); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Recovery could not be saved. Keep this tab open and try again.']);
}
