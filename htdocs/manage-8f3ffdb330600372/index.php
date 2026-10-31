<?php
/**
 * Admin dashboard: statistics and quick actions.
 */

require __DIR__ . '/includes/secure.php';

$stats = stats_counts();

$GLOBALS['admin_actions'] = '<a class="btn btn-primary btn-sm" href="' . e(admin_url('notes/edit.php')) . '">New note</a>'
    . '<a class="btn btn-sm" href="' . e(admin_url('past-papers/edit.php')) . '">Add past paper</a>';

admin_header('Dashboard', 'dashboard');
?>

<div class="stat-grid">
  <div class="stat-card">
    <span class="stat-value"><?= (int) $stats['subjects'] ?></span>
    <span class="stat-label">Subjects</span>
  </div>
  <div class="stat-card">
    <span class="stat-value"><?= (int) $stats['notes'] ?></span>
    <span class="stat-label">Notes</span>
    <?php if ((int) $stats['drafts'] > 0): ?>
      <span class="stat-note"><?= (int) $stats['drafts'] ?> draft<?= (int) $stats['drafts'] === 1 ? '' : 's' ?></span>
    <?php endif; ?>
  </div>
  <div class="stat-card">
    <span class="stat-value"><?= (int) $stats['papers'] ?></span>
    <span class="stat-label">Past Papers</span>
  </div>
  <div class="stat-card">
    <span class="stat-value"><?= (int) $stats['images'] ?></span>
    <span class="stat-label">Images</span>
  </div>
  <div class="stat-card">
    <span class="stat-value"><?= (int) $stats['topics'] ?></span>
    <span class="stat-label">Topics</span>
  </div>
</div>

<section class="panel">
  <h2>Quick actions</h2>
  <div class="quick-actions">
    <a class="btn" href="<?= e(admin_url('subjects/edit.php')) ?>">Create subject</a>
    <a class="btn" href="<?= e(admin_url('topics/edit.php')) ?>">Create topic</a>
    <a class="btn" href="<?= e(admin_url('notes/edit.php')) ?>">Create note</a>
    <a class="btn" href="<?= e(admin_url('past-papers/edit.php')) ?>">Add past paper</a>
    <a class="btn" href="<?= e(admin_url('images/index.php')) ?>">Manage images</a>
  </div>
</section>

<section class="panel">
  <h2>Latest notes</h2>
  <?php $latest = q_all('SELECT n.id, n.title, n.slug, n.status, n.updated_at, s.name AS subject_name FROM notes n JOIN subjects s ON s.id = n.subject_id ORDER BY n.updated_at DESC LIMIT 8'); ?>
  <?php if (!$latest): ?>
    <p class="empty">No notes yet. Create your first note to get started.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>Title</th><th>Subject</th><th>Status</th><th>Updated</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($latest as $row): ?>
          <tr>
            <td><?= e($row['title']) ?></td>
            <td><?= e($row['subject_name']) ?></td>
            <td><span class="pill pill-<?= $row['status'] === 'published' ? 'published' : 'draft' ?>"><?= e(ucfirst($row['status'])) ?></span></td>
            <td><?= e(format_date($row['updated_at'])) ?></td>
            <td class="table-actions">
              <a class="btn btn-sm" href="<?= e(admin_url('notes/edit.php?id=' . (int) $row['id'])) ?>">Edit</a>
              <?php if ($row['status'] === 'published'): ?>
                <a class="btn btn-sm" href="<?= e(url('/notes/' . $row['slug'])) ?>" target="_blank" rel="noopener">View</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php admin_footer();
