<?php
/** Public ad placement regression checks, without contacting the provider. */
declare(strict_types=1);
ob_start();
define('APP_BOOTSTRAPPED',true);
$settings=[];
function config(string $key,$default=null) { global $settings; return $settings[$key] ?? $default; }
function check(bool $condition,string $message): void { if (!$condition) { throw new RuntimeException($message); } echo 'PASS: '.$message.PHP_EOL; }
require dirname(__DIR__).'/htdocs/includes/functions.php';
require dirname(__DIR__).'/htdocs/includes/ads.php';
function placement(string $type): string { ob_start(); render_ad_placement($type); return ob_get_clean(); }
http_response_code(200);
$GLOBALS['nav_active']='home';
$definitions=ad_definitions();
check($definitions['skyscraper']===['key'=>'d86f3395419921337f7012c9a80fe8b3','width'=>160,'height'=>600],'160 by 600 placement uses the supplied tag');
check($definitions['leaderboard']===['key'=>'e1673f86092846b19df46fcca69e6330','width'=>728,'height'=>90],'728 by 90 placement uses the supplied tag');
check($definitions['mobile']===['key'=>'356c31bb805cb3b74d6006bfcf65c082','width'=>320,'height'=>50],'320 by 50 mobile placement uses the supplied tag');
$html=placement('leaderboard');
check(strpos($html,'data-ad-width="728"')!==false && strpos($html,'data-ad-height="90"')!==false,'fixed dimensions are reserved before the banner loads');
check(strpos($html,'data-ad-frame="/ad-frame.php?placement=leaderboard"')!==false && strpos($html,'atOptions')===false,'synchronous tags are isolated from the parent document');
$native=placement('native');
check(substr_count($native,'id="container-450869d5ebb53b1108ab92ae905cc15f"')===1 && strpos($native,'https://bauval.org/21/450869d5ebb53b1108ab92ae905cc15f')!==false,'native placement provides exactly one correctly named target');
$settings['app.base_path']='/study';
check(strpos(placement('skyscraper'),'/study/ad-frame.php?placement=skyscraper')!==false,'ad frames respect subfolder installations');
check(placement('invalid')==='','unknown placements cannot inject ad scripts');
$settings['ads.enabled']=false;
check(placement('native')==='' && placement('leaderboard')==='','the global switch disables every placement');
$settings['ads.enabled']=true;
$GLOBALS['nav_active']='search';
check(placement('native')==='' && placement('skyscraper')==='','internal search pages have no advertising');
$GLOBALS['nav_active']=''; http_response_code(404);
check(placement('native')==='','error pages have no advertising');
check(!file_exists(dirname(__DIR__).'/htdocs/assets/js/side-ads.js'),'the previous sidebar loader has been removed');
foreach (['includes/layout.php','includes/components.php'] as $path) {
    $source=file_get_contents(dirname(__DIR__).'/htdocs/'.$path);
    check(strpos($source,'aclib')===false && strpos($source,'data-adcash')===false,'previous provider embeds removed from '.$path);
}
echo "All ad placement checks passed.\n";
ob_end_flush();
