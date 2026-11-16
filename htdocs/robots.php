<?php
/** Dynamic robots.txt, including staging protection and sitemap discovery. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/seo.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$base = rtrim((string) config('app.base_path', ''), '/');
echo "User-agent: *\n";
if (!config('app.indexable', true)) {
    echo "Disallow: /\n";
    exit;
}
echo 'Disallow: ' . $base . "/config/\n";
echo 'Disallow: ' . $base . "/includes/\n";
echo 'Disallow: ' . $base . "/install.php\n";
// Search and admin pages use noindex; allow crawlers to see that directive.
echo "\nSitemap: " . seo_absolute_url(asset('sitemap.xml')) . "\n";
