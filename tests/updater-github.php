<?php
require __DIR__.'/bootstrap.php';require_once ABSPATH.'wp-admin/includes/file.php';LPM_Updater::boot();$checks=0;
function check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);echo 'PASS '.(++$checks).' '.$label."\n";}
function rejects($fn,$label){try{$fn();}catch(Throwable $e){check(true,$label);return;}throw new RuntimeException($label);}
$repo='Enggarnanto/price-catalog';$api='https://api.github.com/repos/'.$repo.'/releases/latest';$base='https://github.com/'.$repo.'/releases/download/v2.3.1/';$zipUrl=$base.'latis-price-catalogs-2.3.1.zip';$sha=str_repeat('a',64);$mode='digest';
$zipAsset=['name'=>'latis-price-catalogs-2.3.1.zip','state'=>'uploaded','browser_download_url'=>$zipUrl,'digest'=>'sha256:'.$sha];
$release=['tag_name'=>'v2.3.1','draft'=>false,'prerelease'=>false,'body'=>'Rilis GitHub uji','assets'=>[$zipAsset]];
$meta=['slug'=>'price-catalogs','version'=>'2.3.1','requires'=>'6.2','requires_php'=>'8.1','download_url'=>$zipUrl,'sha256'=>$sha,'changelog'=>'<p>GitHub test</p>'];
$original=get_site_option(LPM_Updater::OPTION,null);
$mock=static function($pre,$args,$url)use(&$release,&$mode,&$meta,$api,$base,$sha){
 if($url===$api)return ['headers'=>[],'body'=>wp_json_encode($release),'response'=>['code'=>$mode==='missing'?404:200]];
 if($url===$base.'price-catalogs-update.json')return ['headers'=>[],'body'=>wp_json_encode($meta),'response'=>['code'=>200]];
 if($url===$base.'latis-price-catalogs-2.3.1.zip.sha256')return ['headers'=>[],'body'=>$sha.'  latis-price-catalogs-2.3.1.zip','response'=>['code'=>200]];
 return $pre;
};add_filter('pre_http_request',$mock,10,3);
try{
 check(LPM_Updater::repository('https://github.com/'.$repo.'.git')===$repo,'Repository URL normalizes to owner/repo');
 rejects(fn()=>LPM_Updater::repository('https://example.com/owner/repo'),'Foreign repository URL is rejected');
 rejects(fn()=>LPM_Updater::repository('owner/../../repo'),'Repository traversal syntax is rejected');
 LPM_Updater::save_settings(['source'=>'github','github_repository'=>'https://github.com/'.$repo.'.git','enabled'=>1]);
 check(LPM_Updater::source_url()===$api,'GitHub source uses latest stable release API');
 $data=LPM_Updater::check(true);check(!is_wp_error($data)&&$data['version']==='2.3.1'&&$data['sha256']===$sha,'Stable ZIP asset digest creates validated update metadata');
 check($data['download_url']===$zipUrl&&!empty($data['requirements_from_zip']),'GitHub asset URL and ZIP requirement checks are retained');
 $offer=LPM_Updater::inject((object)[]);check($offer->response[LPM_Updater::plugin()]->package===$zipUrl,'GitHub release integrates with WordPress update offer');
 $release['assets'][0]['digest']=null;$release['assets'][]=['name'=>'latis-price-catalogs-2.3.1.zip.sha256','browser_download_url'=>$base.'latis-price-catalogs-2.3.1.zip.sha256'];
 check(!is_wp_error(LPM_Updater::check(true)),'SHA-256 companion asset works when GitHub digest is unavailable');
 $release['assets']=[$zipAsset,['name'=>'price-catalogs-update.json','browser_download_url'=>$base.'price-catalogs-update.json']];$meta['requires_php']='99.0';
 $data=LPM_Updater::check(true);check(!is_wp_error($data)&&!LPM_Updater::compatible($data),'Optional release manifest provides exact runtime requirements');
 check(LPM_Updater::inject((object)[])->response[LPM_Updater::plugin()]->package==='','GitHub manifest incompatibility removes installable ZIP');
 $meta['requires_php']='8.1';$meta['sha256']=str_repeat('b',64);check(is_wp_error(LPM_Updater::check(true)),'Companion manifest cannot contradict asset digest');$meta['sha256']=$sha;
 $meta['version']='2.3.2';check(is_wp_error(LPM_Updater::check(true)),'Companion manifest must match release tag');$meta['version']='2.3.1';
 $release['assets']=[$zipAsset];$release['prerelease']=true;check(is_wp_error(LPM_Updater::check(true)),'Prerelease is excluded');$release['prerelease']=false;
 $release['draft']=true;check(is_wp_error(LPM_Updater::check(true)),'Draft release is excluded');$release['draft']=false;
 $release['assets']=[];check(is_wp_error(LPM_Updater::check(true)),'Source code archive is never treated as installable plugin ZIP');
 $release['assets']=[$zipAsset];$release['assets'][0]['browser_download_url']='https://github.com/other/repo/releases/download/v2.3.1/plugin.zip';check(is_wp_error(LPM_Updater::check(true)),'Release asset cannot point to another repository');
 $release['assets']=[$zipAsset];$release['assets'][0]['digest']=null;check(is_wp_error(LPM_Updater::check(true)),'Missing digest and checksum assets fail closed');
 $release['assets']=[$zipAsset];$mode='missing';$error=LPM_Updater::check(true);check(is_wp_error($error)&&str_contains($error->get_error_message(),'release')||is_wp_error($error)&&str_contains($error->get_error_message(),'Release'),'Missing latest release explains public/stable release requirement');
 $file=__DIR__.'/runtime/updater/requirements.zip';$zip=new ZipArchive();$zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE);$zip->addFromString('price-catalogs/price-catalogs.php',"<?php\n/**\n * Plugin Name: Latis Price Catalogs\n * Version: 2.3.1\n * Requires PHP: 99.0\n */\n");$zip->close();
 rejects(fn()=>LPM_Updater::verify_zip($file,['version'=>'2.3.1','sha256'=>hash_file('sha256',$file)]),'ZIP header requirements are enforced even without a release manifest');
 echo 'SUCCESS '.$checks." GitHub updater checks\n";
}finally{remove_filter('pre_http_request',$mock,10);$original===null?delete_site_option(LPM_Updater::OPTION):update_site_option(LPM_Updater::OPTION,$original);delete_site_transient(LPM_Updater::CACHE);delete_site_option('lpm_updater_last_check');}
