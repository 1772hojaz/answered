<?php
/**
 * AJAX endpoint: save an external image link (no file is uploaded).
 * Returns JSON: {"ok": true, "url": "https://…/photo.jpg"}
 */

require dirname(__DIR__) . '/includes/boot.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (!is_admin()) {
    respond(['ok' => false, 'error' => 'Not authenticated.'], 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(['ok' => false, 'error' => 'POST required.'], 405);
}

// verify_csrf() (from boot.php) has already checked the token from the
// POST body or the X-CSRF-Token header and terminated the request if invalid.

$url = (string) ($_POST['url'] ?? '');
$name = isset($_POST['name']) ? (string) $_POST['name'] : null;

$result = save_image_link($url, 'note', $name);

if (!$result['ok']) {
    respond(['ok' => false, 'error' => (string) ($result['error'] ?? 'Could not save the image link.')]);
}

respond(['ok' => true, 'url' => (string) $result['url']]);
