<?php
/**
 * Shared helpers: URLs, escaping, CSRF, slugs, flash messages and the
 * read-side queries used by the public pages.
 */

declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/* ---------------------------------------------------------------- URLs -- */

/**
 * Build an application URL. Uses clean URLs when enabled in config,
 * otherwise falls back to index.php?p=... which needs no mod_rewrite.
 *
 * @param array $query
 */
function url(string $path = '/', array $query = []): string
{
    $base = rtrim((string) config('app.base_path', ''), '/');
    $path = '/' . ltrim($path, '/');
    $qs   = $query ? ('?' . http_build_query($query)) : '';

    if (!config('app.clean_urls', true)) {
        $target = $base . '/index.php';
        if ($path === '/') {
            return $target . $qs;
        }
        return $target . '?' . http_build_query(['p' => $path] + $query);
    }

    if ($path === '/') {
        return ($base === '' ? '/' : $base . '/') . (strpos($qs, '?') === 0 && $qs === '?' ? '' : $qs);
    }
    return $base . $path . $qs;
}

/**
 * Public base URL of the (secretly named) admin area.
 *
 * The admin folder is not called "admin" so it cannot be found by guessing;
 * the name lives in config('app.admin_path') and must match the real folder.
 */
function admin_base_url(): string
{
    $base = rtrim((string) config('app.base_path', ''), '/');
    $slug = trim((string) config('app.admin_path', 'admin'), '/');
    if ($slug === '') {
        $slug = 'admin';
    }

    return $base . '/' . $slug;
}

/**
 * URL of a file inside the admin area (admin pages are real files).
 */
function admin_url(string $path = ''): string
{
    return admin_base_url() . '/' . ltrim($path, '/');
}

/**
 * Action URL for a GET form. When pretty URLs are off, browsers drop the
 * query string of a form action on submit, so the route travels in a hidden
 * field instead (see route_field()).
 */
function form_action(string $path): string
{
    if (config('app.clean_urls', true)) {
        return url($path);
    }
    return rtrim((string) config('app.base_path', ''), '/') . '/index.php';
}

/**
 * Hidden route input paired with form_action(); empty when pretty URLs are on.
 */
function route_field(string $path): string
{
    if (config('app.clean_urls', true)) {
        return '';
    }
    return '<input type="hidden" name="p" value="' . e($path) . '">';
}

/**
 * Absolute-ish path (root relative) for assets such as CSS and JS.
 */
function asset(string $path): string
{
    $base = rtrim((string) config('app.base_path', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * Prefix a root-relative path (e.g. /uploads/...) with the configured
 * base path so links also work when installed in a subfolder.
 */
function public_path(string $path): string
{
    if ($path === '' || strpos($path, '/') !== 0) {
        return $path;
    }
    $base = rtrim((string) config('app.base_path', ''), '/');
    if ($base === '' || strpos($path, $base . '/') === 0 || $path === $base) {
        return $path;
    }
    return $base . $path;
}

/**
 * Redirect to a path inside the admin area (already base-path aware).
 */
function admin_redirect(string $pathAndQuery = 'index.php'): void
{
    header('Location: ' . admin_url($pathAndQuery));
    exit;
}

function redirect(string $path, array $query = []): void
{
    header('Location: ' . url($path, $query));
    exit;
}

/**
 * The current request path as a route key, e.g. "subjects/mathematics".
 */
function current_path(): string
{
    // Explicit route parameter (clean_urls = false).
    if (isset($_GET['p'])) {
        return trim(rawurldecode((string) $_GET['p']), '/');
    }

    // Fall back to the request path so that URLs also work when
    // mod_rewrite is active while clean_urls is disabled.
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
    $base = rtrim((string) config('app.base_path', ''), '/');
    if ($base !== '' && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    $path = preg_replace('#^/?index\.php$#', '', $path) ?? $path;

    return trim(rawurldecode($path), '/');
}

/* ------------------------------------------------------------- Escaping -- */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ------------------------------------------------------------- Sessions -- */

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * @return array[] list of ['type' => ..., 'message' => ...]
 */
function flash_take(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ----------------------------------------------------------------- CSRF -- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Rejects any state-changing request whose token does not match.
 */
function verify_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Your session token has expired. Go back, refresh the page and try again.');
    }
}

/* ---------------------------------------------------------------- Slugs -- */

function slugify(string $text): string
{
    $text = trim($text);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($converted) && $converted !== '') {
            $text = $converted;
        }
    }
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text === '' ? 'item' : mb_substr($text, 0, 180, 'UTF-8');
}

/**
 * Make sure a slug is unique inside a table column, appending -2, -3, ...
 *
 * @param int|null $excludeId row id to ignore (when editing)
 */
function unique_slug(string $table, string $column, string $slug, ?int $excludeId = null): string
{
    $candidate = $slug;
    $i = 1;

    while (true) {
        $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?" . ($excludeId !== null ? ' AND id <> ?' : '');
        $params = $excludeId !== null ? [$candidate, $excludeId] : [$candidate];
        $count = (int) q_one($sql, $params);
        if ($count === 0) {
            return $candidate;
        }
        $i++;
        $candidate = $slug . '-' . $i;
    }
}

/* ---------------------------------------------------------- Formatting --- */

function format_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('j F Y', $ts) : '';
}

function excerpt_from_html(string $html, int $length = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '…';
}

function bytes_to_human(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return ($i === 0 ? (string) (int) $size : number_format($size, 1)) . ' ' . $units[$i];
}

/* ---------------------------------------------------- Read-side queries --- */

function subjects_list(): array
{
    return q_all('SELECT * FROM subjects ORDER BY sort_order, name');
}

function subject_by_slug(string $slug): ?array
{
    return q_row('SELECT * FROM subjects WHERE slug = ?', [$slug]);
}

function subject_by_id(int $id): ?array
{
    return q_row('SELECT * FROM subjects WHERE id = ?', [$id]);
}

function topics_for_subject(int $subjectId): array
{
    return q_all('SELECT * FROM topics WHERE subject_id = ? ORDER BY sort_order, name', [$subjectId]);
}

function topic_by_id(int $id): ?array
{
    return q_row('SELECT * FROM topics WHERE id = ?', [$id]);
}

function notes_for_subject(int $subjectId, string $status = 'published', ?int $topicId = null): array
{
    $sql = 'SELECT n.*, t.name AS topic_name, t.slug AS topic_slug
            FROM notes n
            LEFT JOIN topics t ON t.id = n.topic_id
            WHERE n.subject_id = ? AND n.status = ?';
    $params = [$subjectId, $status];
    if ($topicId !== null) {
        $sql .= ' AND n.topic_id = ?';
        $params[] = $topicId;
    }
    $sql .= ' ORDER BY COALESCE(t.sort_order, 9999), t.name, n.title';
    return q_all($sql, $params);
}

function recent_notes(int $limit = 8): array
{
    return q_all(
        'SELECT n.*, s.name AS subject_name, s.slug AS subject_slug
         FROM notes n
         JOIN subjects s ON s.id = n.subject_id
         WHERE n.status = ?
         ORDER BY COALESCE(n.published_at, n.updated_at) DESC, n.id DESC
         LIMIT ' . max(1, (int) $limit),
        ['published']
    );
}

function note_by_slug(string $slug): ?array
{
    return q_row(
        'SELECT n.*, s.name AS subject_name, s.slug AS subject_slug,
                t.name AS topic_name, t.slug AS topic_slug
         FROM notes n
         JOIN subjects s ON s.id = n.subject_id
         LEFT JOIN topics t ON t.id = n.topic_id
         WHERE n.slug = ? AND n.status = ?',
        [$slug, 'published']
    );
}

function note_by_id(int $id): ?array
{
    return q_row(
        'SELECT n.*, s.name AS subject_name, s.slug AS subject_slug,
                t.name AS topic_name, t.slug AS topic_slug
         FROM notes n
         JOIN subjects s ON s.id = n.subject_id
         LEFT JOIN topics t ON t.id = n.topic_id
         WHERE n.id = ?',
        [$id]
    );
}

function papers_for_subject(int $subjectId): array
{
    return q_all(
        'SELECT * FROM past_papers WHERE subject_id = ? ORDER BY year DESC, paper_number, title',
        [$subjectId]
    );
}

function recent_papers(int $limit = 8): array
{
    return q_all(
        'SELECT p.*, s.name AS subject_name, s.slug AS subject_slug
         FROM past_papers p
         JOIN subjects s ON s.id = p.subject_id
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT ' . max(1, (int) $limit)
    );
}

function paper_by_slug(string $slug): ?array
{
    return q_row(
        'SELECT p.*, s.name AS subject_name, s.slug AS subject_slug
         FROM past_papers p
         JOIN subjects s ON s.id = p.subject_id
         WHERE p.slug = ?',
        [$slug]
    );
}

function paper_by_id(int $id): ?array
{
    return q_row('SELECT * FROM past_papers WHERE id = ?', [$id]);
}

function all_papers_paginated(int $page, int $perPage = 20): array
{
    $offset = max(0, ($page - 1) * $perPage);
    $total = (int) q_one('SELECT COUNT(*) FROM past_papers');
    $rows = q_all(
        'SELECT p.*, s.name AS subject_name, s.slug AS subject_slug
         FROM past_papers p
         JOIN subjects s ON s.id = p.subject_id
         ORDER BY p.year DESC, p.paper_number, p.title
         LIMIT ' . $perPage . ' OFFSET ' . $offset
    );
    return [$rows, $total];
}

function stats_counts(): array
{
    return [
        'subjects'   => (int) q_one('SELECT COUNT(*) FROM subjects'),
        'topics'     => (int) q_one('SELECT COUNT(*) FROM topics'),
        'notes'      => (int) q_one('SELECT COUNT(*) FROM notes'),
        'drafts'     => (int) q_one("SELECT COUNT(*) FROM notes WHERE status = 'draft'"),
        'papers'     => (int) q_one('SELECT COUNT(*) FROM past_papers'),
        'images'     => (int) q_one('SELECT COUNT(*) FROM images'),
        'users'      => (int) q_one('SELECT COUNT(*) FROM users'),
    ];
}

/**
 * Public search across subjects, notes and past papers.
 *
 * @return array{subjects: array[], notes: array[], papers: array[]}
 */
function search_all(string $term, int $limit = 25): array
{
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term) . '%';

    $subjects = q_all(
        'SELECT * FROM subjects WHERE name LIKE ? OR description LIKE ? ORDER BY name LIMIT ' . $limit,
        [$like, $like]
    );

    $notes = q_all(
        'SELECT n.id, n.title, n.slug, n.content, s.name AS subject_name, s.slug AS subject_slug
         FROM notes n
         JOIN subjects s ON s.id = n.subject_id
         WHERE n.status = ? AND (n.title LIKE ? OR n.content LIKE ?)
         ORDER BY n.title LIMIT ' . $limit,
        ['published', $like, $like]
    );

    $papers = q_all(
        'SELECT p.id, p.title, p.slug, p.year, p.exam_board, p.paper_number,
                s.name AS subject_name, s.slug AS subject_slug
         FROM past_papers p
         JOIN subjects s ON s.id = p.subject_id
         WHERE p.title LIKE ? OR p.description LIKE ? OR s.name LIKE ?
            OR p.year LIKE ? OR p.exam_board LIKE ? OR p.paper_number LIKE ?
         ORDER BY p.year DESC, p.title LIMIT ' . $limit,
        [$like, $like, $like, $like, $like, $like]
    );

    return ['subjects' => $subjects, 'notes' => $notes, 'papers' => $papers];
}

/**
 * Subjects with their published note and past-paper counts, for navigation
 * menus and sidebars.
 */
function subjects_with_counts(): array
{
    return q_all(
        'SELECT s.*,
                (SELECT COUNT(*) FROM notes n
                  WHERE n.subject_id = s.id AND n.status = ?) AS note_count,
                (SELECT COUNT(*) FROM past_papers p
                  WHERE p.subject_id = s.id) AS paper_count
         FROM subjects s
         ORDER BY s.sort_order, s.name',
        ['published']
    );
}

/**
 * Related notes from the same subject, excluding the current note.
 */
function related_notes(int $subjectId, int $excludeNoteId, int $limit = 6): array
{
    return q_all(
        'SELECT n.id, n.title, n.slug, n.updated_at, t.name AS topic_name
         FROM notes n
         LEFT JOIN topics t ON t.id = n.topic_id
         WHERE n.subject_id = ? AND n.status = ? AND n.id <> ?
         ORDER BY COALESCE(n.published_at, n.updated_at) DESC, n.id DESC
         LIMIT ' . max(1, (int) $limit),
        [$subjectId, 'published', $excludeNoteId]
    );
}
