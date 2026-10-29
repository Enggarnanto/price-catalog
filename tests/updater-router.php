<?php
// Test-only loopback server. Never include this harness in an installable plugin.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if(str_starts_with($path,'/price-catalogs/assets/')||str_starts_with($path,'/wp-admin/css/')||str_starts_with($path,'/wp-includes/')){require __DIR__.'/router.php';exit;}
require __DIR__.'/bootstrap.php';wp_installing(false);LPM_Updater::boot();
add_filter('pre_http_request',static function($pre,$args,$url){
    if($url==='https://api.github.com/repos/Enggarnanto/price-catalog/releases/latest')return ['headers'=>[],'body'=>wp_json_encode(['tag_name'=>'v2.4.1','draft'=>false,'prerelease'=>false,'body'=>'Rilis uji GitHub updater.','assets'=>[['name'=>'latis-price-catalogs-2.4.1.zip','state'=>'uploaded','digest'=>'sha256:'.str_repeat('a',64),'browser_download_url'=>'https://github.com/Enggarnanto/price-catalog/releases/download/v2.4.1/latis-price-catalogs-2.4.1.zip']]]),'response'=>['code'=>200]];
    if(!str_starts_with($url,'https://example.com/'))return $pre;
    if(str_contains($url,'error.json'))return ['headers'=>[],'body'=>'Error fixture','response'=>['code'=>503]];
    $manifest=['slug'=>'price-catalogs','version'=>str_contains($url,'current.json')?LPM_VERSION:'2.4.1','requires'=>'6.2','requires_php'=>str_contains($url,'incompatible.json')?'99.0':'8.1','download_url'=>'https://example.com/release.zip','sha256'=>str_repeat('a',64),'changelog'=>'<p>Rilis uji updater.</p>'];
    return ['headers'=>[],'body'=>wp_json_encode($manifest),'response'=>['code'=>200]];
},10,3);
if(isset($_GET['reset'])){delete_site_option(LPM_Updater::OPTION);delete_site_transient(LPM_Updater::CACHE);delete_site_option('lpm_updater_last_check');}
if($path==='/wp-admin/admin-post.php'){
    $action=sanitize_key($_POST['action']??'');if(!in_array($action,['lpm_updater_save','lpm_updater_check'],true)){http_response_code(404);exit;}
    do_action('admin_post_'.$action);exit;
}
header('Cache-Control: no-store');
?><!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Updater local test</title><link rel="stylesheet" href="/wp-admin/css/common.css"><link rel="stylesheet" href="/wp-admin/css/forms.css"><link rel="stylesheet" href="/wp-admin/css/buttons.css"><link rel="stylesheet" href="/price-catalogs/assets/updater.css"><body class="wp-core-ui" style="margin:20px;background:#f6f8fd"><?php LPM_Updater::page(); ?></body></html>
