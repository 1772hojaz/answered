<?php
/**
 * Add a new past paper or edit an existing one (metadata + external PDF link).
 */

require __DIR__ . '/../includes/secure.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$paper = $id > 0 ? paper_by_id($id) : null;

if ($id > 0 && $paper === null) {
    flash_set('error', 'Past paper not found.');
    admin_redirect('past-papers/index.php');
}

if ($paper && !empty($paper['deleted_at'])) { flash_set('error','This paper is in the trash. Restore it first.'); admin_redirect('trash.php?type=papers'); }
$metadata = $paper ?? [];
$examSession = (string)($paper['exam_session'] ?? '');
$subjects = subjects_list();
if (!$subjects) {
    flash_set('error', 'Create a subject before adding past papers.');
    admin_redirect('subjects/edit.php');
}

$title = (string) ($paper['title'] ?? '');
$subjectId = (int) ($paper['subject_id'] ?? $subjects[0]['id']);
$year = (int) ($paper['year'] ?? 0);
$examBoard = (string) ($paper['exam_board'] ?? 'ZIMSEC');
$paperNumber = (string) ($paper['paper_number'] ?? '');
$description = (string) ($paper['description'] ?? '');
$pdfUrl = (string) ($paper['file_path'] ?? '');
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try { $metadata = resource_metadata($_POST); } catch (InvalidArgumentException $invalid) { $error = $invalid->getMessage(); }
    $examSession = trim((string)($_POST['exam_session'] ?? ''));
    if (mb_strlen($examSession,'UTF-8')>50) { $error='The exam session is too long.'; }
    $title = trim((string) ($_POST['title'] ?? ''));
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $year = (int) ($_POST['year'] ?? 0);
    $examBoard = trim((string) ($_POST['exam_board'] ?? ''));
    $paperNumber = trim((string) ($_POST['paper_number'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $pdfUrl = trim((string) ($_POST['pdf_url'] ?? ''));

    if ($title === '') {
        $error = 'Please enter a title.';
    } elseif (mb_strlen($title, 'UTF-8') > 200) {
        $error = 'The title must be 200 characters or fewer.';
    } elseif (subject_by_id($subjectId) === null) {
        $error = 'Please choose a subject.';
    } elseif ($year !== 0 && ($year < 1900 || $year > 2100)) {
        $error = 'Please enter a valid year (or leave it empty).';
    } elseif (mb_strlen($examBoard,'UTF-8') > 100 || mb_strlen($paperNumber,'UTF-8') > 50 || mb_strlen($description,'UTF-8') > 2000) {
        $error = 'The examination board, paper number, or description is too long.';
    }

    $link = $pdfUrl !== '' ? validate_paper_url($pdfUrl) : ['ok' => false];

    if ($error === '' && $pdfUrl === '' && $paper === null) {
        $error = 'Please paste a link to the PDF.';
    } elseif ($error === '' && $pdfUrl !== '' && !$link['ok']) {
        $error = (string) $link['error'];
    }

    if ($error === '') {
        db()->beginTransaction();
        try {
        if ($paper === null) {
            $slug = unique_slug('past_papers', 'slug', slugify($title));
            q(
                'INSERT INTO past_papers (subject_id, title, slug, year, exam_board, paper_number, description, file_path, file_size, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())',
                [
                    $subjectId,
                    $title,
                    $slug,
                    $year ?: null,
                    $examBoard !== '' ? $examBoard : null,
                    $paperNumber !== '' ? $paperNumber : null,
                    $description !== '' ? $description : null,
                    (string) $link['url'],
                ]
            );
            $id = (int) db()->lastInsertId();
            flash_set('success', 'Past paper added.');
        } else {
            $filePath = $link['ok'] ? (string) $link['url'] : (string) $paper['file_path'];
            q(
                'UPDATE past_papers SET subject_id = ?, title = ?, year = ?, exam_board = ?, paper_number = ?, description = ?, file_path = ?, file_size = 0, updated_at = NOW() WHERE id = ?',
                [
                    $subjectId,
                    $title,
                    $year ?: null,
                    $examBoard !== '' ? $examBoard : null,
                    $paperNumber !== '' ? $paperNumber : null,
                    $description !== '' ? $description : null,
                    $filePath,
                    (int) $paper['id'],
                ]
            );
            flash_set('success', 'Past paper updated.');
        }

        q('UPDATE past_papers SET level=?,syllabus_code=?,exam_session=?,seo_title=?,seo_description=? WHERE id=?',[$metadata['level'],$metadata['syllabus_code'],$examSession,$metadata['seo_title'],$metadata['seo_description'],$id]);
        workspace_audit($paper ? 'update_paper' : 'create_paper','past_papers',$id,$title);
        db()->commit();
        admin_redirect('past-papers/index.php');
        } catch (Throwable $failure) { db()->rollBack(); error_log($failure->getMessage()); $error='Could not save this paper. Please try again.'; }
    }
}

$GLOBALS['admin_actions'] = '<a class="btn btn-sm" href="' . e(admin_url('past-papers/index.php')) . '">All past papers</a>';

admin_header($paper ? 'Edit Past Paper' : 'Add Past Paper', 'papers');
?>

<?php if ($error !== ''): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<form class="form-card" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <div class="form-grid">
    <label class="field">
      <span>Subject *</span>
      <select name="subject_id" required>
        <?php foreach ($subjects as $subject): ?>
          <option value="<?= (int) $subject['id'] ?>" <?= $subjectId === (int) $subject['id'] ? 'selected' : '' ?>>
            <?= e($subject['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="field">
      <span>Title *</span>
      <input type="text" name="title" value="<?= e($title) ?>" required maxlength="200" placeholder="e.g. 2025 Mathematics Paper 1">
    </label>

    <label class="field">
      <span>Year</span>
      <input type="number" name="year" value="<?= $year ?: '' ?>" min="1900" max="2100" placeholder="e.g. 2025">
    </label>

    <label class="field">
      <span>Examination board</span>
      <input type="text" name="exam_board" value="<?= e($examBoard) ?>" maxlength="100" placeholder="e.g. ZIMSEC">
    </label>

    <label class="field">
      <span>Paper number</span>
      <input type="text" name="paper_number" value="<?= e($paperNumber) ?>" maxlength="50" placeholder="e.g. Paper 2">
    </label>
  </div>

  <?php resource_metadata_fields($metadata); ?>
  <label class="field"><span>Exam session</span><input type="text" name="exam_session" maxlength="50" value="<?= e($examSession) ?>" placeholder="e.g. June or November"></label>

  <label class="field">
    <span>Description</span>
    <textarea name="description" rows="3" maxlength="2000" placeholder="Optional notes for students"><?= e($description) ?></textarea>
  </label>

  <label class="field">
    <span>PDF link <?= $paper ? '(leave blank to keep the current link)' : '*' ?></span>
    <?php if ($paper && !empty($paper['file_path'])): ?>
      <span class="field-note">
        Current link: <a href="<?= e((string) $paper['file_path']) ?>" target="_blank" rel="noopener"><?= e((string) $paper['file_path']) ?></a>
      </span>
    <?php endif; ?>
    <input type="url" name="pdf_url" value="<?= e($pdfUrl) ?>" maxlength="2000" placeholder="https://example.com/papers/2025-paper-1.pdf" <?= $paper ? '' : 'required' ?>>
    <small>Paste a link to a PDF hosted elsewhere (Google Drive, Dropbox, your school website…). Nothing is uploaded to this server.</small>
  </label>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= $paper ? 'Save changes' : 'Add past paper' ?></button>
    <a class="btn" href="<?= e(admin_url('past-papers/index.php')) ?>">Cancel</a>
  </div>
</form>

<?php admin_footer();
