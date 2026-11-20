<?php
/** Public advertising placements. Provider keys are public ad identifiers. */
declare(strict_types=1);
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; }

function ad_definitions(): array
{
    return [
        'skyscraper' => ['key'=>'d86f3395419921337f7012c9a80fe8b3','width'=>160,'height'=>600],
        'leaderboard' => ['key'=>'e1673f86092846b19df46fcca69e6330','width'=>728,'height'=>90],
        'mobile' => ['key'=>'356c31bb805cb3b74d6006bfcf65c082','width'=>320,'height'=>50],
        'native' => ['key'=>'450869d5ebb53b1108ab92ae905cc15f'],
    ];
}

function public_ads_enabled(): bool
{
    return (bool) config('ads.enabled', true)
        && ($GLOBALS['nav_active'] ?? '') !== 'search' && http_response_code() < 400;
}

function render_ad_placement(string $placement): void
{
    $definitions=ad_definitions();
    if (!isset($definitions[$placement]) || !public_ads_enabled()) {
        return;
    }
    $ad=$definitions[$placement];
    ?>
    <aside class="ad-placement ad-placement--<?= e($placement) ?>" aria-label="Advertisement">
      <span class="ad-placement-label">Advertisement</span>
      <?php if ($placement === 'native'): ?>
        <div class="ad-native-content" id="container-<?= e($ad['key']) ?>" data-native-ad="https://bauval.org/21/<?= e($ad['key']) ?>"></div>
      <?php else: ?>
        <div class="ad-frame-slot" style="--ad-width:<?= (int)$ad['width'] ?>px;--ad-height:<?= (int)$ad['height'] ?>px"
             data-ad-frame="<?= e(asset('ad-frame.php') . '?placement=' . $placement) ?>"
             data-ad-width="<?= (int)$ad['width'] ?>" data-ad-height="<?= (int)$ad['height'] ?>"></div>
      <?php endif; ?>
    </aside>
    <?php
}
