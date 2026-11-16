<?php
/** Staff permissions, content history, forms, and pagination. */
declare(strict_types=1);
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; }

function full_admin(): bool { return (current_admin()['role'] ?? '') === 'admin'; }
function staff_forbidden(string $message = 'You do not have permission to perform this action.'): void {
    http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); exit($message);
}
function require_full_admin(): void { if (!full_admin()) { staff_forbidden(); } }
function can_edit_note(array $note): bool {
    return empty($note['deleted_at']) && (full_admin() || ($note['status'] === 'draft' && (int) $note['author_id'] === (int) current_admin()['id']));
}
function workspace_audit(string $action, string $type, ?int $id, string $summary): void {
    $actor = current_admin();
    q('INSERT INTO admin_activity (user_id, actor_name, action, entity_type, entity_id, summary) VALUES (?, ?, ?, ?, ?, ?)',
        [$actor['id'], $actor['username'], $action, $type, $id, mb_substr($summary, 0, 255, 'UTF-8')]);
}
function note_snapshot(array $note, string $reason): void {
    $snapshot = array_intersect_key($note, array_flip(['title','content','subject_id','topic_id','level','syllabus_code','seo_title','seo_description']));
    $encoded=json_encode($snapshot, JSON_INVALID_UTF8_SUBSTITUTE);
    $latest=q_one('SELECT snapshot FROM note_revisions WHERE note_id=? ORDER BY id DESC LIMIT 1',[$note['id']]);
    if (is_string($latest) && hash_equals($latest,$encoded)) { return; }
    q('INSERT INTO note_revisions (note_id, user_id, snapshot, reason) VALUES (?, ?, ?, ?)',
        [$note['id'], current_admin()['id'], $encoded, $reason]);
}
function trash_resource(string $table, int $id): void {
    require_full_admin();
    if (!in_array($table, ['notes','past_papers'], true)) { throw new InvalidArgumentException('Invalid resource'); }
    db()->beginTransaction();
    try {
        $row = q_row('SELECT * FROM ' . $table . ' WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id]);
        if ($row) {
            if ($table === 'notes') { note_snapshot($row, 'Before moving to trash'); }
            q('UPDATE ' . $table . ' SET deleted_at = NOW(), updated_at = NOW()' . ($table === 'notes' ? ', version = version + 1' : '') . ' WHERE id = ?', [$id]);
            workspace_audit('trash', $table, $id, 'Moved to trash: ' . $row['title']);
        }
        db()->commit();
    } catch (Throwable $error) { db()->rollBack(); throw $error; }
}
function resource_metadata(array $source): array {
    $values = [];
    foreach (['level'=>20,'syllabus_code'=>30,'seo_title'=>160,'seo_description'=>255] as $key=>$limit) {
        $value = trim((string) ($source[$key] ?? ''));
        if (mb_strlen($value, 'UTF-8') > $limit) { throw new InvalidArgumentException(str_replace('_',' ',ucfirst($key)) . ' is too long.'); }
        $values[$key] = $value;
    }
    if (!in_array($values['level'], ['', 'O Level', 'A Level'], true)) { throw new InvalidArgumentException('Choose a valid study level.'); }
    return $values;
}
function resource_metadata_fields(array $values): void { ?>
  <fieldset class="metadata-panel"><legend>ZIMSEC classification</legend><div class="form-grid">
    <label class="field"><span>Study level</span><select name="level"><option value="">Not specified</option><?php foreach (['O Level','A Level'] as $level): ?><option value="<?= e($level) ?>" <?= ($values['level'] ?? '') === $level ? 'selected' : '' ?>><?= e($level) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Syllabus code</span><input type="text" name="syllabus_code" value="<?= e($values['syllabus_code'] ?? '') ?>" maxlength="30" placeholder="Optional syllabus code"></label>
  </div></fieldset>
  <details class="metadata-panel" data-seo-panel><summary>Search appearance</summary><p class="field-note">Leave these blank to use the automatic ZIMSEC title and description.</p>
    <label class="field"><span>SEO title</span><input type="text" name="seo_title" value="<?= e($values['seo_title'] ?? '') ?>" maxlength="160" data-seo-title><small>ZIMSEC is included automatically when missing.</small></label>
    <label class="field"><span>SEO description</span><textarea name="seo_description" rows="3" maxlength="255" data-seo-description><?= e($values['seo_description'] ?? '') ?></textarea></label>
    <div class="search-preview" aria-label="Search preview"><small><?= e(config('app.name', 'Answered')) ?></small><strong data-seo-preview-title>Automatic ZIMSEC resource title</strong><p data-seo-preview-description>Automatic description based on your resource content.</p></div>
  </details>
<?php }
function admin_pagination(int $total, int $page, int $perPage = 25): void {
    $pages = max(1, (int) ceil($total / $perPage));
    echo '<nav class="pagination" aria-label="Pagination">';
    if ($page > 1) { echo '<a class="btn btn-sm" href="?' . e(http_build_query(array_merge($_GET, ['page'=>$page-1]))) . '">Previous</a>'; }
    echo '<span class="pagination-status">' . $total . ' results · Page ' . $page . ' of ' . $pages . '</span>';
    if ($page < $pages) { echo '<a class="btn btn-sm" href="?' . e(http_build_query(array_merge($_GET, ['page'=>$page+1]))) . '">Next</a>'; }
    echo '</nav>';
}
function render_recovery_list(): void {
    $recoveries=q_all('SELECT * FROM note_autosaves WHERE user_id=? ORDER BY saved_at DESC LIMIT 10',[current_admin()['id']]);
    if (!$recoveries) { return; }
    echo '<section class="panel"><h2>Recovered work</h2><p class="field-note">Open a recovery to review it before saving. Recovery never changes published content.</p><ul class="recovery-list">';
    foreach ($recoveries as $recovery) {
        if ($recovery['note_id']) { $note=note_by_id((int)$recovery['note_id']); if (!$note || !can_edit_note($note)) { continue; } }
        $data=json_decode($recovery['payload'],true);
        $link=$recovery['note_id'] ? 'notes/edit.php?id='.$recovery['note_id'] : 'notes/edit.php?draft='.$recovery['draft_key'];
        echo '<li><a href="'.e(admin_url($link)).'">'.e($data['title'] ?: 'Untitled recovered note').'</a><small>'.e($recovery['saved_at']).' UTC</small></li>';
    }
    echo '</ul></section>';
}
