<?php
// Development-only UI harness. This file is excluded from the installable plugin.
// It deliberately signs in the isolated test administrator; bind only to loopback.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (isset($_GET['rest_route'])) { $path = '/wp-json' . $_GET['rest_route']; }
$workspace = dirname(__DIR__);
$core = getenv('LPM_WP_CORE');
$candidate = null;
if (str_starts_with($path, '/price-catalogs/assets/')) { $candidate = $workspace . $path; $allowed = realpath($workspace . '/price-catalogs/assets'); }
elseif (preg_match('#^/(wp-includes|wp-admin)/(js|css|images)/#', $path)) { $candidate = $core . $path; $allowed = realpath($core . '/' . explode('/', ltrim($path, '/'))[0]); }
elseif (str_starts_with($path, '/content/uploads/')) { $candidate = __DIR__ . '/runtime' . $path; $allowed = realpath(__DIR__ . '/runtime/content/uploads'); }
if ($candidate) {
    $resolved = realpath($candidate);
    if (!$allowed || !$resolved || !str_starts_with(str_replace('\\','/',$resolved), str_replace('\\','/',$allowed) . '/') || !is_file($resolved)) { http_response_code(404); exit; }
    $ext = pathinfo($resolved, PATHINFO_EXTENSION);
    $mime = ['js'=>'application/javascript','css'=>'text/css','png'=>'image/png','svg'=>'image/svg+xml','woff'=>'font/woff','woff2'=>'font/woff2','gif'=>'image/gif','jpg'=>'image/jpeg'];
    if (!isset($mime[$ext])) { http_response_code(403); exit; }
    header('Content-Type: ' . $mime[$ext]); readfile($resolved); exit;
}
require __DIR__ . '/bootstrap.php';
if(isset($_GET['_writer_test'])||($_COOKIE['lpm_writer_test']??'')==='1'){
    $fixture=json_decode(file_get_contents(__DIR__.'/runtime/writer-fixture.json'),true);
    wp_set_current_user((int)$fixture['user']);
}
if($path==='/writer'){setcookie('lpm_writer_test','1');require __DIR__.'/writer-page.php';exit;}
if (str_starts_with($path, '/wp-json/')) {
    $request = new WP_REST_Request($_SERVER['REQUEST_METHOD'], substr($path, strlen('/wp-json')));
    $request->set_query_params($_GET);
    $request->set_body(file_get_contents('php://input'));
    $request->set_headers(getallheaders());
    $response = rest_do_request($request);
    $response = apply_filters('rest_post_dispatch', $response, rest_get_server(), $request);
    http_response_code($response->get_status());
    foreach ($response->get_headers() as $name=>$value) { header($name . ': ' . $value); }
    header('Content-Type: application/json'); echo wp_json_encode($response->get_data()); exit;
}
if ($path === '/wp-admin/admin-ajax.php') {
    require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
    if (($_REQUEST['action'] ?? '') === 'query-attachments') { wp_ajax_query_attachments(); }
    http_response_code(404); exit;
}
if ($path !== '/') { http_response_code(404); exit; }
wp_enqueue_media();
wp_enqueue_script('lpm-grid-tools','/price-catalogs/assets/grid-tools.js',[],LPM_VERSION,true);
wp_enqueue_script('lpm-carousel','/price-catalogs/assets/carousel.js',[],LPM_VERSION,true);
wp_enqueue_script('lpm-export-tools','/price-catalogs/assets/export-tools.js',[],LPM_VERSION,true);
wp_enqueue_style('lpm-editor','/price-catalogs/assets/editor.css',[],LPM_VERSION);
wp_enqueue_script('lpm-admin', '/price-catalogs/assets/admin.js', ['jquery','media-editor','lpm-grid-tools','lpm-carousel','lpm-export-tools'], LPM_VERSION, true);
wp_localize_script('lpm-admin','LPM',['root'=>isset($_GET['plain'])?'http://127.0.0.1:18317/?rest_route=/lpm/v1':'http://127.0.0.1:18317/wp-json/lpm/v1','nonce'=>wp_create_nonce('wp_rest'),'canManage'=>true,'screen'=>'lpm','version'=>LPM_VERSION,'author'=>'Galih Enggarnanto','publicCss'=>'http://127.0.0.1:18317/price-catalogs/assets/public.css','publicScript'=>'http://127.0.0.1:18317/price-catalogs/assets/carousel.js','styling'=>LPM_Styling::get()]);
header('Cache-Control: no-store');
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LPM isolated UI test</title><link rel="stylesheet" href="/wp-admin/css/common.css"><link rel="stylesheet" href="/wp-admin/css/forms.css"><link rel="stylesheet" href="/wp-admin/css/buttons.css"><link rel="stylesheet" href="/price-catalogs/assets/public.css"><link rel="stylesheet" href="/price-catalogs/assets/admin.css"><?php wp_print_styles(); wp_print_head_scripts(); ?></head><body class="wp-core-ui" style="padding:24px"><p>LOCAL TEST — isolated database, test administrator</p><?php LPM_Admin::page(); wp_print_footer_scripts(); wp_print_media_templates(); ?></body></html>
