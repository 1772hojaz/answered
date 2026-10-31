<?php
/**
 * Administrator logout (POST only, CSRF protected).
 */

require __DIR__ . '/includes/boot.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    admin_logout();
}

header('Location: ' . admin_url('login.php'));
exit;
