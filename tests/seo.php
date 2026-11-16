<?php
/** SEO helper regression checks; no database required. Run: php tests/seo.php */
declare(strict_types=1);
ob_start();
define('APP_BOOTSTRAPPED', true);
$settings = ['app.site_url' => 'https://answered.qzz.io', 'app.name' => 'Answered', 'app.clean_urls' => false];
function config(string $key, $default = null) { global $settings; return $settings[$key] ?? $default; }
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
require dirname(__DIR__) . '/htdocs/includes/functions.php';
require dirname(__DIR__) . '/htdocs/includes/seo.php';

$_SERVER['HTTP_HOST'] = 'attacker.example';
check(seo_absolute_url(url('/subjects')) === 'https://answered.qzz.io/index.php?p=%2Fsubjects', 'canonical URLs use the configured origin, not the Host header');
check(seo_absolute_url(url('/past-papers', ['page' => 2])) === 'https://answered.qzz.io/index.php?p=%2Fpast-papers&page=2', 'paginated URLs preserve their page number');
$settings['app.clean_urls'] = true;
$settings['app.base_path'] = '/study';
check(seo_absolute_url(url('/subjects')) === 'https://answered.qzz.io/study/subjects', 'clean URLs retain the installation subfolder');
foreach (['javascript:alert(1)', 'https://user:pass@example.com', 'https://example.com/subfolder', 'https://example.com?x=1', 'https://example.com#fragment'] as $invalid) {
    $settings['app.site_url'] = $invalid;
    check(seo_origin() === 'https://answered.qzz.io', 'invalid canonical origin rejected');
}
$settings['app.site_url'] = 'http://127.0.0.1:8000/';
check(seo_origin() === 'http://127.0.0.1:8000', 'local preview origin and port supported');
$description = seo_description('<p>ZIMSEC Biology &amp; cells</p> ' . str_repeat('revision ', 60));
check(mb_strlen($description, 'UTF-8') <= 160 && strpos($description, 'ZIMSEC Biology & cells') === 0, 'descriptions are readable, entity-decoded, and bounded');
check(seo_description('ZIMSEC Mathematics.') === 'ZIMSEC Mathematics.', 'short descriptions remain intact');
$json = seo_json(['headline' => '</script><script>alert("x")</script>']);
check(strpos($json, '</script>') === false && json_decode($json, true)['headline'] === '</script><script>alert("x")</script>', 'structured data cannot break out of its script element');

$settings['app.indexable'] = true;
http_response_code(200);
check(strpos(seo_robots(), 'noindex') === false, 'production public pages allow indexing');
$GLOBALS['page_noindex'] = true;
check(seo_robots() === 'noindex, follow', 'search pages remain crawlable but not indexable');
unset($GLOBALS['page_noindex']);
http_response_code(404);
check(seo_robots() === 'noindex, follow', 'error responses are not indexable');
http_response_code(200);
$settings['app.indexable'] = false;
check(seo_robots() === 'noindex, nofollow', 'staging overrides page-level indexing');

$settings['app.site_url'] = 'https://answered.qzz.io';
$settings['app.base_path'] = '';
$GLOBALS['seo_breadcrumbs'] = [['label' => 'Home', 'href' => url('/')], ['label' => 'Mathematics', 'href' => url('/subjects/mathematics')], ['label' => 'Algebra'], ['label' => 'Quadratics']];
$GLOBALS['seo_resource'] = ['@type' => ['Article', 'LearningResource'], 'headline' => 'Quadratics'];
$canonical = seo_absolute_url(url('/notes/quadratics'));
$graph = seo_structured_data('Quadratics - ZIMSEC Mathematics Notes', 'ZIMSEC revision notes.', $canonical)['@graph'];
check($graph[1]['url'] === $canonical && $graph[3]['mainEntityOfPage']['@id'] === $graph[1]['@id'], 'resource and page structured data reference the canonical URL');
$crumbs = $graph[2]['itemListElement'];
check(count($crumbs) === 3 && $crumbs[2]['position'] === 3 && $crumbs[2]['item'] === $canonical, 'breadcrumbs omit non-navigable intermediate topics and retain consecutive positions');
echo "All SEO checks passed.\n";
ob_end_flush();
