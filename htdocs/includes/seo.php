<?php
/** Search metadata shared by public pages and the sitemap. */
declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/** Use the configured origin, never an untrusted Host header. */
function seo_origin(): string
{
    $origin = rtrim((string) config('app.site_url', 'https://answered.qzz.io'), '/');
    $parts = parse_url($origin);
    if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])
        || !empty($parts['path'])) {
        return 'https://answered.qzz.io';
    }
    return $origin;
}

function seo_absolute_url(string $path): string
{
    return seo_origin() . '/' . ltrim($path, '/');
}

function seo_description(string $text, int $limit = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    if (mb_strlen($text, 'UTF-8') <= $limit) {
        return $text;
    }
    $short = mb_substr($text, 0, $limit - 1, 'UTF-8');
    $space = mb_strrpos($short, ' ', 0, 'UTF-8');
    return rtrim($space !== false && $space > $limit / 2 ? mb_substr($short, 0, $space, 'UTF-8') : $short, ' ,.;:') . '…';
}

function seo_robots(): string
{
    if (!config('app.indexable', true)) {
        return 'noindex, nofollow';
    }
    return !empty($GLOBALS['page_noindex']) || http_response_code() >= 400
        ? 'noindex, follow' : 'index, follow, max-image-preview:large';
}

/** Encode JSON safely even when a note title contains HTML or </script>. */
function seo_json(array $value): string
{
    return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
}

function seo_structured_data(string $title, string $description, string $canonical): array
{
    $home = seo_absolute_url(url('/'));
    $website = ['@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home,
        'name' => (string) config('app.name', 'Answered'), 'inLanguage' => 'en-ZW',
        'description' => 'ZIMSEC revision notes, subjects, and past papers for students in Zimbabwe.'];
    $page = ['@type' => $GLOBALS['seo_page_type'] ?? 'WebPage', '@id' => $canonical . '#webpage',
        'url' => $canonical, 'name' => $title, 'description' => $description,
        'inLanguage' => 'en-ZW', 'isPartOf' => ['@id' => $home . '#website']];
    $graph = [$website, $page];
    $crumbs = $GLOBALS['seo_breadcrumbs'] ?? [];
    $items = [];
    foreach ($crumbs as $i => $crumb) {
        // A topic without its own landing page is not a navigable ancestor.
        if (empty($crumb['href']) && $i < count($crumbs) - 1) {
            continue;
        }
        $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1,
            'name' => $crumb['label'], 'item' => !empty($crumb['href']) ? seo_absolute_url($crumb['href']) : $canonical];
    }
    if (count($items) > 1) {
        $graph[] = ['@type' => 'BreadcrumbList', '@id' => $canonical . '#breadcrumbs', 'itemListElement' => $items];
        $graph[1]['breadcrumb'] = ['@id' => $canonical . '#breadcrumbs'];
    }
    if (!empty($GLOBALS['seo_resource'])) {
        $resource = $GLOBALS['seo_resource'];
        $resource['@id'] = $canonical . '#resource';
        $resource['url'] = $canonical;
        $resource['inLanguage'] = 'en-ZW';
        $resource['mainEntityOfPage'] = ['@id' => $canonical . '#webpage'];
        $graph[1]['mainEntity'] = ['@id' => $resource['@id']];
        $graph[] = $resource;
    }
    return ['@context' => 'https://schema.org', '@graph' => $graph];
}
