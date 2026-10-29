<?php
require __DIR__.'/bootstrap.php';require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
LPM_Updater::boot();$checks=0;$requests=0;$mode='ok';$version='2.3.1';$feed='https://example.com/lpm-update.json';$package='https://example.com/lpm-plugin.zip';
function check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);echo 'PASS '.(++$checks).' '.$label."\n";}
function rejects($fn,$label){try{$fn();}catch(Throwable $e){check(true,$label);return;}throw new RuntimeException($label);}
$folder=__DIR__.'/runtime/updater';if(!is_dir($folder))mkdir($folder,0777,true);
function archive(string $name,string $version,array $extras=[],string $root='price-catalogs'): string {
 global $folder;$file=$folder.'/'.$name.'.zip';$zip=new ZipArchive();$zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE);$zip->addFromString($root.'/price-catalogs.php',"<?php\n/**\n * Plugin Name: Latis Price Catalogs\n * Version: $version\n */\n");foreach($extras as $key=>$value)$zip->addFromString($key,$value);$zip->close();return $file;
}
$zip=archive('valid',$version,['price-catalogs/readme.txt'=>'Updater test']);$bytes=file_get_contents($zip);
$manifest=['slug'=>'price-catalogs','version'=>$version,'requires'=>'6.2','requires_php'=>'8.1','download_url'=>$package,'sha256'=>hash('sha256',$bytes),'changelog'=>'<p>Release test</p><script>alert(1)</script>'];
$originalSettings=get_site_option(LPM_Updater::OPTION,null);$originalUpdate=get_site_transient('update_plugins');
$http=static function($pre,$args,$url)use(&$mode,&$requests,&$manifest,$feed,$package,&$bytes){
 if(!str_starts_with($url,'https://example.com/'))return $pre;$requests++;
 if($mode==='network')return new WP_Error('fixture_network','Network test');
 if($mode==='redirect')return ['headers'=>['location'=>'https://127.0.0.1/private'],'body'=>'','response'=>['code'=>302]];
 if($url===$feed){$body=$mode==='malformed'?'{broken':($mode==='oversize'?str_repeat('a',131073):wp_json_encode($manifest));return ['headers'=>[],'body'=>$body,'response'=>['code'=>200]];}
 if($url===$package){if(!empty($args['stream']))file_put_contents($args['filename'],$bytes);return ['headers'=>[],'body'=>!empty($args['stream'])?'':$bytes,'response'=>['code'=>200]];}
 return new WP_Error('fixture_url','Unknown test URL');
};add_filter('pre_http_request',$http,10,3);
try{
 delete_site_option(LPM_Updater::OPTION);delete_site_transient(LPM_Updater::CACHE);
 check(!LPM_Updater::settings()['enabled']&&is_wp_error(LPM_Updater::check()),'Updater defaults to disabled with no remote source');
 foreach(['http://example.com/update.json','https://127.0.0.1/update','https://10.0.0.1/update','https://user:pass@example.com/update','https://example.com:8443/update'] as $url)rejects(fn()=>LPM_Updater::url($url),'Reject unsafe source '.$url);
 rejects(fn()=>LPM_Updater::save_settings(['enabled'=>1]),'Cannot enable updater without a manifest URL');
 LPM_Updater::save_settings(['enabled'=>1,'manifest_url'=>$feed]);$data=LPM_Updater::check(true);
 check(!is_wp_error($data)&&$data['version']===$version,'HTTPS JSON source returns a validated update');
 check(!str_contains($data['changelog'],'<script>'),'Release notes strip executable markup');
 $count=$requests;LPM_Updater::check();check($requests===$count,'Repeated checks reuse the six-hour cache');
 $other=(object)['new_version'=>'9.0'];$transient=(object)['response'=>['other/plugin.php'=>$other],'checked'=>[LPM_Updater::plugin()=>LPM_VERSION]];
 $transient=LPM_Updater::inject($transient);check($transient->response[LPM_Updater::plugin()]->new_version===$version,'New release appears in the WordPress update response');
 check($transient->response['other/plugin.php']===$other,'Other plugin updates are preserved');
 $info=LPM_Updater::plugin_info(false,'plugin_information',(object)['slug'=>'price-catalogs']);check($info->version===$version&&$info->download_link===$package,'Plugin details use the same validated source');
 check(LPM_Updater::plugin_info('other','plugin_information',(object)['slug'=>'another-plugin'])==='other','Other plugin details remain untouched');
 LPM_Updater::verify_zip($zip,$data);check(true,'ZIP checksum, root, main file and version verify');
 $bad=$data;$bad['sha256']=str_repeat('0',64);rejects(fn()=>LPM_Updater::verify_zip($zip,$bad),'Checksum mismatch rejects update');
 $wrong=archive('wrong-root',$version,[],'other-plugin');$bad=$data;$bad['sha256']=hash_file('sha256',$wrong);rejects(fn()=>LPM_Updater::verify_zip($wrong,$bad),'Wrong plugin folder is rejected');
 $traversal=archive('traversal',$version,['price-catalogs/../../outside.php'=>'bad']);$bad=$data;$bad['sha256']=hash_file('sha256',$traversal);rejects(fn()=>LPM_Updater::verify_zip($traversal,$bad),'ZIP path traversal is rejected');
 $wrongVersion=archive('wrong-version','1.0.0');$bad=$data;$bad['sha256']=hash_file('sha256',$wrongVersion);rejects(fn()=>LPM_Updater::verify_zip($wrongVersion,$bad),'ZIP version must match release manifest');
 $upgrader=new WP_Upgrader(new Automatic_Upgrader_Skin());$download=$upgrader->download_package($package,false,['plugin'=>LPM_Updater::plugin()]);
 check(!is_wp_error($download)&&hash_file('sha256',$download)===$data['sha256'],'Native WordPress upgrader receives the verified ZIP');if(is_string($download))wp_delete_file($download);
 check(LPM_Updater::download(false,$package,null,['plugin'=>'other/plugin.php'])===false,'Download hook ignores unrelated plugins');
 check(is_wp_error(LPM_Updater::download(false,'https://example.com/changed.zip',null,['plugin'=>LPM_Updater::plugin()])),'Changed package URL cannot bypass manifest');
 $bytes.='tampered';$result=LPM_Updater::download(false,$package,null,['plugin'=>LPM_Updater::plugin()]);check(is_wp_error($result),'Tampered downloaded package is blocked before extraction');$bytes=file_get_contents($zip);
 $manifest['requires_php']='99.0';LPM_Updater::check(true);$incompatible=LPM_Updater::inject((object)[]);check($incompatible->response[LPM_Updater::plugin()]->package==='','Incompatible PHP release has no installable package');
 check(is_wp_error(LPM_Updater::download(false,$package,null,['plugin'=>LPM_Updater::plugin()])),'Download also blocks incompatible runtime');$manifest['requires_php']='8.1';
 $manifest['version']=LPM_VERSION;LPM_Updater::check(true);$same=LPM_Updater::inject((object)[]);check(!isset($same->response[LPM_Updater::plugin()])&&isset($same->no_update[LPM_Updater::plugin()]),'Current release is marked as no update');
 $manifest['version']='1.0.0';LPM_Updater::check(true);$older=LPM_Updater::inject((object)[]);check(!isset($older->response[LPM_Updater::plugin()]),'Older feed never advertises a downgrade');$manifest['version']=$version;
 foreach(['malformed','oversize','network','redirect'] as $failure){$mode=$failure;check(is_wp_error(LPM_Updater::check(true)),'Bad manifest transport is reported: '.$failure);check(!isset(LPM_Updater::inject((object)[])->response[LPM_Updater::plugin()]),'Failed check removes stale update offer: '.$failure);}
 $mode='ok';$manifest['sha256']='invalid';check(is_wp_error(LPM_Updater::check(true)),'Manifest without a valid checksum is rejected');$manifest['sha256']=hash('sha256',$bytes);
 $manifest['slug']='another';check(is_wp_error(LPM_Updater::check(true)),'Wrong manifest slug is rejected');$manifest['slug']='price-catalogs';
 $user=wp_insert_user(['user_login'=>'updater_writer_'.bin2hex(random_bytes(4)),'user_pass'=>bin2hex(random_bytes(12)),'role'=>'author']);wp_set_current_user($user);
 check(!LPM_Updater::can_manage(),'Author cannot configure updater');rejects(fn()=>LPM_Updater::save_settings(['enabled'=>1,'manifest_url'=>$feed]),'Settings writes require administrator capability');wp_set_current_user(1);
 LPM_Updater::save_settings(['enabled'=>0,'manifest_url'=>$feed]);$disabled=LPM_Updater::inject($transient);check(!isset($disabled->response[LPM_Updater::plugin()])&&isset($disabled->response['other/plugin.php']),'Disabling updater removes only its own update entry');
 ob_start();LPM_Updater::page();$page=ob_get_clean();check(str_contains($page,'Updater belum aktif')&&str_contains($page,'Galih Enggarnanto'),'Settings page shows truthful initial state and creator');
 $manifest['version']=$version;LPM_Updater::save_settings(['enabled'=>1,'manifest_url'=>$feed]);LPM_Updater::refresh();ob_start();LPM_Updater::page();$page=ob_get_clean();
 check(str_contains($page,'Update ke v'.$version)&&str_contains($page,'action=upgrade-plugin')&&str_contains($page,'_wpnonce='),'Compatible release links to native nonce-protected install');
 delete_site_transient(LPM_Updater::CACHE);ob_start();LPM_Updater::page();$stale=ob_get_clean();check(str_contains($stale,'Perlu cek update kembali')&&!str_contains($stale,'Update ke v'.$version),'Expired feed does not expose a stale install action');
 file_put_contents(dirname(__DIR__).'/artifacts/updater-test-state.json',wp_json_encode(['version'=>$version,'sha256'=>hash('sha256',$bytes)]));
 echo 'SUCCESS '.$checks." updater checks on WordPress with HTTP fixtures\n";
}finally{
 remove_filter('pre_http_request',$http,10);$originalSettings===null?delete_site_option(LPM_Updater::OPTION):update_site_option(LPM_Updater::OPTION,$originalSettings);delete_site_transient(LPM_Updater::CACHE);delete_site_option('lpm_updater_last_check');remove_all_filters('pre_set_site_transient_update_plugins');remove_all_filters('site_transient_update_plugins');if($originalUpdate!==false)set_site_transient('update_plugins',$originalUpdate);else delete_site_transient('update_plugins');
}
