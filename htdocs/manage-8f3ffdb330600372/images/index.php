<?php
/**
 * Image library: save external image links, view them, delete unused ones.
 */

require __DIR__ . '/../includes/secure.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $result = save_image_link(
            (string) ($_POST['url'] ?? ''),
            'note',
            (string) ($_POST['name'] ?? '')
        );
        if ($result['ok']) {
            flash_set('success', !empty($result['exists']) ? 'That image link is already saved.' : 'Image link saved.');
        } else {
            flash_set('error', (string) $result['error']);
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = delete_image($id);
        if ($result['ok']) {
            flash_set('success', 'Image deleted.');
        } else {
            flash_set('error', (string) ($result['error'] ?? 'Could not delete the image.'));
        }
    }

    admin_redirect('images/index.php');
}

$images = q_all('SELECT * FROM images ORDER BY created_at DESC LIMIT 300');

foreach ($images as $i => $image) {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], (string) $image['path']) . '%';
    $images[$i]['usage'] = (int) q_one('SELECT COUNT(*) FROM notes WHERE content LIKE ?', [$like])
        + (int) q_one('SELECT COUNT(*) FROM subjects WHERE icon_path = ?', [(string) $image['path']]);
}

$GLOBALS['admin_actions'] = '';

admin_header('Images', 'images');
?>

<form class="form-card" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add">
  <div class="inline-upload">
    <label class="field">
      <span>Image link</span>
      <input type="url" name="url" required maxlength="2000" placeholder="https://example.com/diagram.png">
      <small>Paste a link to an image hosted elsewhere. Nothing is uploaded to this server. Images can also be inserted directly from the note editor.</small>
    </label>
    <label class="field">
      <span>Name (optional)</span>
      <input type="text" name="name" maxlength="255" placeholder="e.g. Parabola diagram">
    </label>
    <button type="submit" class="btn btn-primary">Save link</button>
  </div>
</form>

<?php if (!$images): ?>
  <p class="empty">No image links saved yet.</p>
<?php else: ?>
  <div class="image-grid">
    <?php foreach ($images as $image): ?>
      <figure class="image-card">
        <a href="<?= e($image['path']) ?>" target="_blank" rel="noopener">
          <img src="<?= e($image['path']) ?>" alt="<?= e($image['original_name'] ?? '') ?>" loading="lazy">
        </a>
        <figcaption>
          <span class="image-name" title="<?= e($image['original_name'] ?? '') ?>"><?= e($image['original_name'] ?? basename((string) $image['path'])) ?></span>
          <span class="image-meta">Link · <?= e(format_date($image['created_at'])) ?></span>
          <span class="image-usage <?= $image['usage'] > 0 ? 'is-used' : '' ?>">
            <?= $image['usage'] > 0 ? 'Used ' . (int) $image['usage'] . '×' : 'Unused' ?>
          </span>
          <form method="post" class="inline-form"
                data-confirm="<?= $image['usage'] > 0 ? 'This image is still used in content. Delete it anyway? (It will only be removed if it is truly unused.)' : 'Delete this image permanently?' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $image['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php admin_footer();
