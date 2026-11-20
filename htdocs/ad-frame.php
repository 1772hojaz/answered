<?php
/** Separate documents preserve the provider's synchronous script semantics. */
declare(strict_types=1);
define('APP_BOOTSTRAPPED', true);
require __DIR__ . '/includes/ads.php';
$placement=$_GET['placement'] ?? '';
$definitions=ad_definitions();
if (!is_string($placement) || !in_array($placement,['skyscraper','leaderboard','mobile'],true)) {
    http_response_code(404); exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET',['GET','HEAD'],true)) {
    http_response_code(405); header('Allow: GET, HEAD'); exit;
}
$ad=$definitions[$placement];
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: frame-ancestors 'self'");
header('Referrer-Policy: strict-origin-when-cross-origin');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Advertisement</title>
<style>html,body{margin:0;padding:0;width:<?= (int)$ad['width'] ?>px;height:<?= (int)$ad['height'] ?>px;overflow:hidden;background:transparent}iframe{border:0;display:block}</style>
</head><body>
<script>
var atOptions = <?= json_encode(['key'=>$ad['key'],'format'=>'iframe','height'=>$ad['height'],'width'=>$ad['width'],'params'=>(object)[]],JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://bauval.org/22/<?= $ad['key'] ?>"></script>
</body></html>
