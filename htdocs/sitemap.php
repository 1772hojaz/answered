<?php
/** Sitemap index and bounded child sitemaps, using the site's canonical URLs. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/seo.php';

$section = (string) ($_GET['section'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$batch = 1000;
$definitions = [
    'subjects' => ['table' => 'subjects', 'fields' => 'slug', 'where' => '', 'params' => []],
    'notes' => ['table' => 'notes', 'fields' => 'slug, updated_at', 'where' => ' WHERE deleted_at IS NULL AND status = ?', 'params' => ['published']],
    'papers' => ['table' => 'past_papers', 'fields' => 'slug, updated_at', 'where' => ' WHERE deleted_at IS NULL', 'params' => []],
];
if ($section !== '' && $section !== 'main' && !isset($definitions[$section])) {
    http_response_code(404);
    exit;
}

// Complete database reads before emitting XML, so failures cannot yield partial sitemaps.
$entries = [];
$isIndex = $section === '';
if ($isIndex) {
    $entries[] = ['loc' => seo_absolute_url(asset('sitemap.php') . '?section=main')];
    foreach ($definitions as $key => $definition) {
        $count = (int) q_one('SELECT COUNT(*) FROM ' . $definition['table'] . $definition['where'], $definition['params']);
        for ($chunk = 1; $chunk <= (int) ceil($count / $batch); $chunk++) {
            $entries[] = ['loc' => seo_absolute_url(asset('sitemap.php') . '?' . http_build_query(['section' => $key, 'page' => $chunk]))];
        }
    }
} elseif ($section === 'main') {
    foreach (['/', '/subjects', '/past-papers'] as $path) {
        $entries[] = ['loc' => seo_absolute_url(url($path))];
    }
    $paperPages = (int) ceil((int) q_one('SELECT COUNT(*) FROM past_papers WHERE deleted_at IS NULL') / 20);
    for ($number = 2; $number <= $paperPages; $number++) {
        $entries[] = ['loc' => seo_absolute_url(url('/past-papers', ['page' => $number]))];
    }
} else {
    $definition = $definitions[$section];
    $rows = q_all('SELECT ' . $definition['fields'] . ' FROM ' . $definition['table'] . $definition['where']
        . ' ORDER BY id LIMIT ' . $batch . ' OFFSET ' . (($page - 1) * $batch), $definition['params']);
    foreach ($rows as $row) {
        $path = ($section === 'papers' ? '/past-papers/' : '/' . $section . '/') . $row['slug'];
        $entry = ['loc' => seo_absolute_url(url($path))];
        if (!empty($row['updated_at']) && ($timestamp = strtotime($row['updated_at'])) !== false) {
            $entry['lastmod'] = date(DATE_W3C, $timestamp);
        }
        $entries[] = $entry;
        if ($section === 'subjects') {
            $entries[] = ['loc' => seo_absolute_url(url($path . '/notes'))];
            $entries[] = ['loc' => seo_absolute_url(url($path . '/past-papers'))];
        }
    }
}

header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: ' . (config('app.indexable', true) ? 'noindex, follow' : 'noindex, nofollow'));
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$root = $isIndex ? 'sitemapindex' : 'urlset';
$element = $isIndex ? 'sitemap' : 'url';
echo '<' . $root . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($entries as $entry) {
    echo '  <' . $element . '>';
    foreach ($entry as $key => $value) {
        echo '<' . $key . '>' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</' . $key . '>';
    }
    echo '</' . $element . ">\n";
}
echo '</' . $root . '>';
