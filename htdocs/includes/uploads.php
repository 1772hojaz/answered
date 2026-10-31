<?php
/**
 * Media references (images and past-paper PDFs).
 *
 * Files are never uploaded to or stored on this server. Admins paste external
 * links (https://…) and only those links are recorded in the database:
 *   - note images ......... images.path
 *   - subject icons ....... subjects.icon_path
 *   - past-paper PDFs ..... past_papers.file_path
 *
 * This keeps the hosting account tiny and avoids writable upload directories.
 */

declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * Validate an external media link. Only absolute http(s) URLs are accepted.
 *
 * @param string   $url
 * @param string[] $schemes allowed URL schemes
 * @return string|null the cleaned URL, or null when invalid
 */
function validate_media_url(string $url, array $schemes = ['http', 'https']): ?string
{
    $url = trim($url);
    // Strip control characters that can smuggle header/attribute breaks.
    $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? '';

    if ($url === '' || strlen($url) > 2000) {
        return null;
    }
    if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m) !== 1) {
        return null;
    }
    if (!in_array(strtolower($m[1]), $schemes, true)) {
        return null;
    }
    // Reject credentials, backslashes and malformed absolute URLs.
    if (strpos($url, '\\') !== false || filter_var($url, FILTER_VALIDATE_URL) === false
        || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
        return null;
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return null;
    }

    return $url;
}

/**
 * Store (or find) an image link in the images table.
 *
 * @return array{ok: bool, error?: string, id?: int, url?: string, exists?: bool}
 */
function save_image_link(string $url, string $kind = 'note', ?string $name = null): array
{
    $kind = $kind === 'subject_icon' ? 'subject_icon' : 'note';

    $url = (string) validate_media_url($url);
    if ($url === '') {
        return ['ok' => false, 'error' => 'Enter a valid image URL starting with http:// or https://.'];
    }

    $existing = q_row('SELECT id FROM images WHERE path = ?', [$url]);
    if ($existing !== null) {
        return ['ok' => true, 'id' => (int) $existing['id'], 'url' => $url, 'exists' => true];
    }

    $label = trim((string) $name);
    if ($label === '') {
        $label = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $label = basename($label);
    }

    q(
        'INSERT INTO images (path, original_name, mime, size_bytes, kind, created_at) VALUES (?, ?, ?, 0, ?, NOW())',
        [$url, $label !== '' ? mb_substr($label, 0, 255, 'UTF-8') : null, 'external', $kind]
    );

    return ['ok' => true, 'id' => (int) db()->lastInsertId(), 'url' => $url, 'exists' => false];
}

/**
 * Store a past-paper PDF link in the past_papers table.
 *
 * @return array{ok: bool, error?: string, url?: string}
 */
function validate_paper_url(string $url): array
{
    $clean = validate_media_url($url, ['http', 'https']);
    if ($clean === null) {
        return ['ok' => false, 'error' => 'Enter a valid PDF link starting with http:// or https://.'];
    }

    return ['ok' => true, 'url' => $clean];
}

/**
 * Does any stored content still reference this image path?
 */
function image_is_used(string $path): bool
{
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $path) . '%';
    $notes = (int) q_one('SELECT COUNT(*) FROM notes WHERE content LIKE ?', [$like]);
    $icons = (int) q_one('SELECT COUNT(*) FROM subjects WHERE icon_path = ?', [$path]);

    return ($notes + $icons) > 0;
}

/**
 * Delete an image record, but only when nothing references it any more.
 * Any legacy local file left over from an older version is removed too.
 *
 * @return array{ok: bool, error?: string}
 */
function delete_image(int $imageId): array
{
    $image = q_row('SELECT * FROM images WHERE id = ?', [$imageId]);
    if ($image === null) {
        return ['ok' => false, 'error' => 'Image not found.'];
    }

    $path = (string) $image['path'];
    if (image_is_used($path)) {
        return ['ok' => false, 'error' => 'This image is still used in a note or subject icon.'];
    }

    $candidate = legacy_upload_file($path);
    if ($candidate !== null) {
        @unlink($candidate);
    }

    q('DELETE FROM images WHERE id = ?', [$imageId]);

    return ['ok' => true];
}

/**
 * Remove a legacy locally-stored past-paper file (only used to clean up
 * records created before media links were introduced).
 */
function delete_paper_file(?string $filePath): void
{
    $candidate = legacy_upload_file($filePath);
    if ($candidate !== null) {
        @unlink($candidate);
    }
}

/** Resolve a legacy file only when its real path stays inside uploads. */
function legacy_upload_file(?string $filePath): ?string
{
    if (!$filePath || strpos($filePath, '/') !== 0
        || preg_match('/[\x00-\x1F\x7F\\\\]/', $filePath)
        || preg_match('#(?:^|/)\.#', $filePath)) {
        return null;
    }
    $base = rtrim((string) config('app.base_path', ''), '/');
    if ($base !== '' && strpos($filePath, $base . '/') === 0) {
        $filePath = substr($filePath, strlen($base));
    }
    if (strpos($filePath, '..') !== false || strpos($filePath, '/uploads/') !== 0) {
        return null;
    }
    $uploadsRoot = realpath(APP_ROOT . '/uploads');
    $candidate = realpath(APP_ROOT . '/' . ltrim($filePath, '/'));

    if ($uploadsRoot !== false && $candidate !== false
        && strpos($candidate, $uploadsRoot . DIRECTORY_SEPARATOR) === 0
        && is_file($candidate)) {
        return $candidate;
    }
    return null;
}
