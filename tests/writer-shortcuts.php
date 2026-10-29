<?php
require __DIR__.'/bootstrap.php';
require_once ABSPATH.'wp-admin/includes/screen.php';
require_once ABSPATH.'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH.WPINC.'/class-wp-admin-bar.php';
LPM_Schema::install();
$checks=0;
function check($ok,$message){global $checks;if(!$ok){throw new RuntimeException($message);}echo 'PASS '.(++$checks).' '.$message."\n";}
$user=wp_insert_user(['user_login'=>'writer_'.bin2hex(random_bytes(4)),'user_pass'=>bin2hex(random_bytes(16)),'role'=>'author']);
check(!is_wp_error($user),'Create test author');
$ids=[];
foreach(['published','draft','deleted'] as $mode){
    $id=LPM_Service::create('price_table','Writer shortcut '.$mode);
    $content=LPM_Content::initial('price_table');$content['headers']=['Paket','Harga'];
    $content['rows']=[[LPM_Content::cell('SD'),LPM_Content::cell('150000')]];
    $save=LPM_Service::save($id,$content,0,0);
    if($mode!=='draft'){$pub=LPM_Service::publish($id,$save['revision'],0,'Fixture','writer_'.bin2hex(random_bytes(12)));}
    if($mode==='deleted'){LPM_Service::delete($id,(int)$pub['editor']['resource']['draft_revision'],(int)$pub['version_id']);}
    $ids[$mode]=$id;
}
wp_set_current_user($user);
LPM_Writer::boot();
$bar=new WP_Admin_Bar();LPM_Writer::admin_bar($bar);
check($bar->get_node('lpm-pricelist')->href===admin_url('admin.php?page=lpm'),'Author gets admin-bar Pricelist shortcut');
check($bar->get_node('lpm-documentation')->href===LPM_Writer::DOCUMENTATION,'Admin-bar documentation uses supplied URL');
$links=LPM_Writer::action_links([]);
check(count($links)===2&&str_contains(implode('',$links),'Dokumentasi'),'Plugin actions include library and documentation');
set_current_screen('post');get_current_screen()->post_type='post';
LPM_Writer::enqueue();LPM_Writer::block_editor();
check(wp_script_is('lpm-writer','enqueued')&&wp_script_is('lpm-writer-block','enqueued'),'Writer assets load on post editor');
check(in_array('wp-edit-post',wp_scripts()->registered['lpm-writer-block']->deps,true),'Block panel supports older and newer editor namespaces');
ob_start();LPM_Writer::classic_button('content');$button=ob_get_clean();
check(str_contains($button,'Sisipkan Pricelist')&&str_contains($button,'data-editor="content"'),'Classic editor button targets intended content area');
$r=new WP_REST_Request('GET','/lpm/v1/resources');$r->set_param('type','price_table');$r->set_param('search','Writer shortcut');$r->set_param('status','published');
$r->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));
check(LPM_Admin::permission($r)===true,'Author may search published library with valid nonce');
$list=LPM_Admin::collection($r);$returned=array_map('intval',array_column($list['items'],'id'));
check(in_array($ids['published'],$returned,true)&&!in_array($ids['draft'],$returned,true)&&!in_array($ids['deleted'],$returned,true),'Writer list excludes unpublished and deleted items');
check(!array_key_exists('draft_revision',$list['items'][0])&&!array_key_exists('draft_data',$list['items'][0]),'Writer search does not expose draft fields');
$write=new WP_REST_Request('POST','/lpm/v1/resources/'.$ids['published'].'/save');$write->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));
check(is_wp_error(LPM_Admin::permission($write)),'Pricelist shortcut does not grant writers price-edit permissions');
set_current_screen('dashboard');
wp_dequeue_script('lpm-writer');wp_dequeue_script('lpm-writer-block');LPM_Writer::enqueue();
check(!wp_script_is('lpm-writer','enqueued'),'Picker assets stay off unrelated screens');
wp_set_current_user(0);$guest=new WP_Admin_Bar();LPM_Writer::admin_bar($guest);
check(!$guest->get_node('lpm-pricelist'),'Visitors do not receive admin shortcuts');
$data=get_file_data(LPM_FILE,['Author'=>'Author','AuthorURI'=>'Author URI','PluginURI'=>'Plugin URI']);
check($data['Author']==='Galih Enggarnanto'&&$data['AuthorURI']==='https://www.mtalkblog.com/'&&$data['PluginURI']===LPM_Writer::DOCUMENTATION,'Author, website and plugin documentation metadata match request');
file_put_contents(__DIR__.'/runtime/writer-fixture.json',wp_json_encode(['user'=>$user,'ids'=>$ids]));
echo 'SUCCESS '.$checks." writer shortcut checks on WordPress + MySQL.\n";
