<?php
/**
 * Whitelist HTML sanitiser for rich-text note content.
 *
 * Note content is sanitised when it is saved and again when it is rendered,
 * so even content written directly to the database cannot inject script
 * into a student's browser.
 */

declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/** Tags removed together with everything inside them. */
const SANITIZE_DROP_TAGS = [
    'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed',
    'applet', 'form', 'input', 'button', 'select', 'option', 'textarea',
    'link', 'meta', 'base', 'noscript', 'svg', 'math', 'audio', 'video',
    'canvas', 'template', 'slot', 'marquee', 'blink',
];

/** Tags that are kept. Anything else is unwrapped (children kept). */
const SANITIZE_ALLOWED_TAGS = [
    'p', 'br', 'hr', 'div', 'span',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'mark', 'small',
    'ul', 'ol', 'li', 'dl', 'dt', 'dd',
    'blockquote', 'pre', 'code', 'kbd', 'samp', 'var',
    'a', 'img', 'figure', 'figcaption',
    'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'col', 'colgroup',
    'sup', 'sub',
];

/** Per-tag attribute whitelist. 'style' and 'align' are always considered. */
const SANITIZE_ALLOWED_ATTRS = [
    'a'          => ['href', 'title', 'target'],
    'img'        => ['src', 'alt', 'width', 'height'],
    'td'         => ['colspan', 'rowspan', 'width', 'height'],
    'th'         => ['colspan', 'rowspan', 'width', 'height', 'scope'],
    'col'        => ['span', 'width'],
    'colgroup'   => ['span'],
    'ol'         => ['start', 'type'],
    'ul'         => ['type'],
    'li'         => ['value'],
    'blockquote' => ['cite'],
    'hr'         => ['width'],
    'table'      => ['width', 'border', 'cellpadding', 'cellspacing'],
];

/** CSS properties allowed inside inline style attributes. */
const SANITIZE_ALLOWED_CSS = [
    'text-align', 'width', 'height', 'max-width',
    'font-size', 'font-weight', 'font-style', 'font-family',
    'color', 'background-color',
    'border', 'border-width', 'border-style', 'border-color', 'border-collapse',
    'padding', 'padding-left', 'padding-right', 'padding-top', 'padding-bottom',
    'margin', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom',
    'vertical-align', 'text-decoration', 'line-height', 'letter-spacing',
];

/**
 * Sanitise a fragment of rich-text HTML. Returns safe HTML.
 */
function sanitize_note_html(?string $html): string
{
    if ($html === null) {
        return '';
    }
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (strlen($html) > 2000000) {
        $html = substr($html, 0, 2000000);
    }

    $previous = libxml_use_internal_errors(true);

    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<?xml encoding="utf-8" ?><html><body id="__sanitizer_root__">' . $html . '</body></html>';
    $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $body = null;
    foreach ($dom->getElementsByTagName('body') as $candidate) {
        if ($candidate->getAttribute('id') === '__sanitizer_root__') {
            $body = $candidate;
            break;
        }
    }
    if ($body === null) {
        return '';
    }

    sanitize_children($body);

    $out = '';
    foreach (iterator_to_array($body->childNodes) as $child) {
        $serialized = $dom->saveHTML($child);
        if (is_string($serialized)) {
            $out .= $serialized;
        }
    }
    $out = trim($out);

    // Belt and braces: nothing dangerous may survive serialisation.
    if (preg_match('/<\s*(script|style|iframe|object|embed|form|link|meta|base)\b/i', $out)) {
        return '';
    }

    return $out;
}

function sanitize_children(DOMNode $parent): void
{
    if (!$parent->hasChildNodes()) {
        return;
    }

    foreach (iterator_to_array($parent->childNodes) as $child) {
        if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
            $parent->removeChild($child);
            continue;
        }

        if (!($child instanceof DOMElement)) {
            continue;
        }

        $tag = strtolower($child->tagName);

        if (in_array($tag, SANITIZE_DROP_TAGS, true)) {
            $parent->removeChild($child);
            continue;
        }

        if (!in_array($tag, SANITIZE_ALLOWED_TAGS, true)) {
            // Unwrap: keep the descendants, drop the unknown container.
            sanitize_children($child);
            while ($child->firstChild !== null) {
                $parent->insertBefore($child->firstChild, $child);
            }
            $parent->removeChild($child);
            continue;
        }

        sanitize_attributes($child);

        if ($tag === 'img' && !$child->hasAttribute('src')) {
            $parent->removeChild($child);
            continue;
        }

        sanitize_children($child);
    }
}

function sanitize_attributes(DOMElement $el): void
{
    $tag = strtolower($el->tagName);
    $perTag = SANITIZE_ALLOWED_ATTRS[$tag] ?? [];
    $alignValue = null;
    $externalLink = false;
    $hasHref = false;

    foreach (iterator_to_array($el->attributes) as $attr) {
        $name = strtolower($attr->name);
        $value = trim($attr->value);

        $isAllowed = $name === 'style' || $name === 'align' || in_array($name, $perTag, true);
        if (!$isAllowed) {
            $el->removeAttribute($attr->name);
            continue;
        }

        switch ($name) {
            case 'style':
                $clean = sanitize_style($value);
                if ($clean === '') {
                    $el->removeAttribute('style');
                } else {
                    $el->setAttribute('style', $clean);
                }
                break;

            case 'align':
                $lower = strtolower($value);
                if (in_array($lower, ['left', 'center', 'right', 'justify'], true)) {
                    $alignValue = $lower;
                }
                $el->removeAttribute('align');
                break;

            case 'href':
                $safe = sanitize_link_url($value);
                if ($safe === null) {
                    $el->removeAttribute('href');
                    $externalLink = false;
                    $hasHref = false;
                } else {
                    $hasHref = true;
                    $el->setAttribute('href', $safe);
                    $externalLink = (bool) preg_match('#^https?:#i', $safe);
                }
                break;

            case 'src':
                $safe = sanitize_image_src($value);
                if ($safe === null) {
                    $el->removeAttribute('src');
                } else {
                    $el->setAttribute('src', $safe);
                }
                break;

            case 'target':
                if (strtolower($value) !== '_blank') {
                    $el->removeAttribute('target');
                }
                break;

            case 'width':
            case 'height':
                if (!preg_match('/^\d{1,4}%?$/', $value)) {
                    $el->removeAttribute($name);
                }
                break;

            case 'colspan':
            case 'rowspan':
            case 'span':
            case 'value':
                if (!preg_match('/^\d{1,3}$/', $value) || (int) $value < 1) {
                    $el->removeAttribute($name);
                }
                break;

            case 'start':
                if (!preg_match('/^\d{1,6}$/', $value)) {
                    $el->removeAttribute($name);
                }
                break;

            case 'border':
            case 'cellpadding':
            case 'cellspacing':
                if (!preg_match('/^\d{1,3}$/', $value)) {
                    $el->removeAttribute($name);
                }
                break;

            case 'alt':
            case 'title':
                $el->setAttribute($name, mb_substr($value, 0, 300, 'UTF-8'));
                break;

            case 'scope':
                if (!in_array(strtolower($value), ['row', 'col', 'rowgroup', 'colgroup'], true)) {
                    $el->removeAttribute($name);
                }
                break;

            case 'cite':
                $safe = sanitize_link_url($value);
                if ($safe === null) {
                    $el->removeAttribute('cite');
                } else {
                    $el->setAttribute('cite', $safe);
                }
                break;

            case 'type':
                $allowedTypes = ['1', 'a', 'A', 'i', 'I', 'disc', 'circle', 'square'];
                if (!in_array(strtolower($value), $allowedTypes, true)) {
                    $el->removeAttribute($name);
                }
                break;
        }
    }

    // External links open safely in a new tab; anything else stays put.
    if ($tag === 'a' && $hasHref && $externalLink) {
        $el->setAttribute('rel', 'noopener noreferrer');
        $el->setAttribute('target', '_blank');
    } elseif ($tag === 'a') {
        $el->removeAttribute('target');
        $el->removeAttribute('rel');
    }

    if ($alignValue !== null) {
        $existing = $el->hasAttribute('style') ? $el->getAttribute('style') : '';
        if (stripos($existing, 'text-align') === false) {
            $merged = sanitize_style(($existing !== '' ? rtrim($existing, '; ') . '; ' : '') . 'text-align: ' . $alignValue);
            if ($merged !== '') {
                $el->setAttribute('style', $merged);
            }
        }
    }
}

/**
 * Keep only whitelisted, value-validated CSS declarations.
 */
function sanitize_style(string $style): string
{
    $style = str_replace(["\n", "\r", "\t"], ' ', $style);
    $declarations = [];

    foreach (explode(';', $style) as $declaration) {
        if (strpos($declaration, ':') === false) {
            continue;
        }
        list($property, $value) = explode(':', $declaration, 2);
        $property = strtolower(trim($property));
        $value = trim($value);

        if (!in_array($property, SANITIZE_ALLOWED_CSS, true)) {
            continue;
        }
        if ($value === '' || strlen($value) > 200) {
            continue;
        }
        if (!preg_match('/^[a-zA-Z0-9 #%.,()\-\/]*$/', $value)) {
            continue;
        }
        if (preg_match('/(expression|javascript|behavior|@import|url)/i', $value)) {
            continue;
        }
        $declarations[] = $property . ': ' . $value;
    }

    return implode('; ', $declarations);
}

/**
 * Allow http(s) and mailto links, plus in-site paths and anchors.
 */
function sanitize_link_url(string $url): ?string
{
    $url = trim($url);
    if (strpos($url, '\\') !== false) {
        return null;
    }
    $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? '';
    if ($url === '' || strlen($url) > 2000) {
        return null;
    }

    if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m)) {
        $scheme = strtolower($m[1]);
        if (!in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return null;
        }
        return $url;
    }

    if (strpos($url, '//') === 0) {
        return null;
    }
    if (isset($url[0]) && ($url[0] === '/' || $url[0] === '#')) {
        return $url;
    }

    return null;
}

/**
 * Allow external http(s) images and legacy locally-stored uploads.
 */
function sanitize_image_src(string $url): ?string
{
    $url = trim($url);
    if (strpos($url, '\\') !== false) {
        return null;
    }
    $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? '';
    if ($url === '' || strlen($url) > 2000) {
        return null;
    }

    if (strpos($url, '//') === 0) {
        return null;
    }

    // Absolute URL: only http(s) is allowed (blocks javascript:, data:, …).
    if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m)) {
        $scheme = strtolower($m[1]);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (!is_string(parse_url($url, PHP_URL_HOST)) || parse_url($url, PHP_URL_HOST) === '') {
            return null;
        }
        return $url;
    }

    // Legacy: files stored under this site's uploads directory.
    $uploadsPrefix = rtrim((string) config('app.base_path', ''), '/') . '/uploads/';
    if (strpos($url, $uploadsPrefix) !== 0) {
        return null;
    }
    if (strpos($url, '..') !== false || strpos($url, '%2e') !== false || strpos($url, "\0") !== false) {
        return null;
    }

    return $url;
}
