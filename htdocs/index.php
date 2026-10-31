<?php
/**
 * Public front controller.
 *
 * Routes:
 *   /                                 home
 *   /subjects                         all subjects
 *   /subjects/{slug}                  subject page (notes + past papers)
 *   /subjects/{slug}/notes            subject page, notes section
 *   /subjects/{slug}/past-papers      subject page, past papers section
 *   /notes/{slug}                     a published note
 *   /past-papers                      all past papers
 *   /past-papers/{slug}               one past paper
 *   /search?q=...                     search results
 *
 * When clean URLs are disabled in config, the same routes are served
 * through index.php?p=/path (no mod_rewrite required).
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/components.php';

$path = current_path();

$routes = [
    ''                            => ['pages/home.php', []],
    'subjects'                    => ['pages/subjects.php', []],
    'search'                      => ['pages/search.php', []],
    'past-papers'                 => ['pages/past_papers.php', []],
];

$segment = explode('/', $path);
$page = null;
$params = [];

if (isset($routes[$path])) {
    list($page, $params) = $routes[$path];
} elseif (count($segment) === 2 && $segment[0] === 'subjects') {
    $page = 'pages/subject.php';
    $params = ['subject_slug' => $segment[1], 'section' => 'all'];
} elseif (count($segment) === 3 && $segment[0] === 'subjects' && $segment[2] === 'notes') {
    $page = 'pages/subject.php';
    $params = ['subject_slug' => $segment[1], 'section' => 'notes'];
} elseif (count($segment) === 3 && $segment[0] === 'subjects' && $segment[2] === 'past-papers') {
    $page = 'pages/subject.php';
    $params = ['subject_slug' => $segment[1], 'section' => 'papers'];
} elseif (count($segment) === 2 && $segment[0] === 'notes') {
    $page = 'pages/note.php';
    $params = ['note_slug' => $segment[1]];
} elseif (count($segment) === 2 && $segment[0] === 'past-papers') {
    $page = 'pages/past_paper.php';
    $params = ['paper_slug' => $segment[1]];
}

ob_start();

if ($page !== null && is_file(__DIR__ . '/' . $page)) {
    extract($params, EXTR_SKIP);
    require __DIR__ . '/' . $page;
} else {
    http_response_code(404);
    require __DIR__ . '/pages/404.php';
}

$body = (string) ob_get_clean();
render_layout($body);
