<?php
/**
 * Admin dashboard: statistics and quick actions.
 */

require __DIR__ . '/includes/secure.php';

if (!full_admin()) {
    $GLOBALS['admin_actions']='<a class="btn btn-primary" href="'.e(admin_url('notes/edit.php')).'">Create draft</a>';
    admin_header('Your drafts','dashboard');
    echo '<p class="field-note">Create and revise your own drafts. An administrator reviews and publishes completed notes.</p>';
    render_recovery_list();
    $drafts=q_all("SELECT id,title,updated_at FROM notes WHERE author_id=? AND status='draft' AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 10",[current_admin()['id']]);
    echo '<section class="panel"><h2>Recently edited drafts</h2>';
    if (!$drafts) { echo '<p class="empty">Create your first draft to get started.</p>'; }
    foreach ($drafts as $draft) { echo '<p><a href="'.e(admin_url('notes/edit.php?id='.$draft['id'])).'">'.e($draft['title']).'</a></p>'; }
    echo '<a class="btn" href="'.e(admin_url('notes/index.php')).'">All your drafts</a></section>';
    admin_footer(); return;
}

$stats = stats_counts();

$GLOBALS['admin_actions'] = '<a class="btn btn-primary btn-sm" href="' . e(admin_url('notes/edit.php')) . '">New note</a>'
    . '<a class="btn btn-sm" href="' . e(admin_url('past-papers/edit.php')) . '">Add past paper</a>';

admin_header('Dashboard', 'dashboard');
?>

<section class="admin-welcome">
  <div><span class="eyebrow section-eyebrow">MAKE ROOM FOR A NEW IDEA</span><h2>Good resources make<br>great starting points.</h2><p>Help a learner take their next step. Add a note, organise a topic, or share a past paper.</p><a class="btn btn-primary" href="<?= e(admin_url('notes/edit.php')) ?>">Create a note <span aria-hidden="true">&rarr;</span></a></div>
  <div class="admin-welcome-art" aria-hidden="true"><span>✦</span><strong>Aa</strong><small>MADE FOR LEARNING</small></div>
</section>

<?php render_recovery_list(); ?>
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
  <?php $latest = q_all('SELECT n.id, n.title, n.slug, n.status, n.updated_at, s.name AS subject_name FROM notes n JOIN subjects s ON s.id = n.subject_id WHERE n.deleted_at IS NULL ORDER BY n.updated_at DESC LIMIT 8'); ?>
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
            <td data-label="Title"><?= e($row['title']) ?></td>
            <td data-label="Subject"><?= e($row['subject_name']) ?></td>
            <td data-label="Status"><span class="pill pill-<?= $row['status'] === 'published' ? 'published' : 'draft' ?>"><?= e(ucfirst($row['status'])) ?></span></td>
            <td data-label="Updated"><?= e(format_date($row['updated_at'])) ?></td>
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
