<?php
/**
 * Bootstrap for every admin page: shared session/CSRF handling plus the
 * admin chrome. Pages that must be behind the login include secure.php
 * instead, which wraps this file with require_admin().
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require __DIR__ . '/layout.php';

// The admin area is private: never index or archive it, even if a crawler
// somehow stumbles on the secret path.
header('X-Robots-Tag: noindex, nofollow, noarchive');

verify_csrf();
