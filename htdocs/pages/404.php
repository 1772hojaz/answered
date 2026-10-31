<?php
/**
 * 404 page.
 */

http_response_code(404);

$GLOBALS['page_title'] = 'Page not found';
$GLOBALS['nav_active'] = '';
?>
<div class="container section not-found">
  <h1>404</h1>
  <p>We could not find that page. It may have been moved or the link may be incorrect.</p>
  <p class="not-found-actions">
    <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go to the homepage</a>
    <a class="btn" href="<?= e(url('/subjects')) ?>">Browse subjects</a>
  </p>
</div>
