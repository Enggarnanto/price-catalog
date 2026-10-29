<?php
require __DIR__.'/bootstrap.php';
LPM_Schema::install();
$checks=0;
function check($ok,$message){global $checks;if(!$ok){throw new RuntimeException($message);}echo 'PASS '.(++$checks).' '.$message."\n";}
function reject($fn,$class,$message){try{$fn();}catch(Throwable $e){check($e instanceof $class,$message);return;}throw new RuntimeException($message);}
function token(){return 'simple_'.bin2hex(random_bytes(16));}
$id=LPM_Service::create('price_table','Simple workflow');
$content=LPM_Content::initial('price_table');$content['program']='reguler';$content['headers']=['Paket','Harga'];
$content['rows']=[[LPM_Content::cell('SD'),LPM_Content::cell('150.000')]];
$save=LPM_Service::save($id,$content,0,0,false,null,['name'=>'Simple workflow','description'=>'']);
check($save['persisted']&&$save['revision']===1&&$save['base']===0&&$save['production']===0,'Draft receipt contains verified content and version counters');
$key=token();$pub=LPM_Service::publish($id,1,0,'Perbarui pricelist',$key);
check($pub['persisted']&&isset($pub['editor']['resource'])&&$pub['editor']['resource']['draft_data']===null,'Publish receipt includes verified editor without a second HTTP GET');
check($pub['editor']['production_content']['rows'][0][1]['text']==='150.000','Verified publication returns complete published matrix');
$retry=LPM_Service::publish($id,1,0,'Perbarui pricelist',$key);
check($retry['version_id']===$pub['version_id']&&$retry['editor']['resource']['production_version_id']==$pub['version_id'],'Lost-response retry returns original version and verified editor');
$editor=LPM_Service::editor($id);$rev=(int)$editor['resource']['draft_revision'];$production=(int)$editor['resource']['production_version_id'];
$before=LPM_Service::history($id);
reject(fn()=>LPM_Service::delete($id,$rev-1,$production),LPM_Conflict::class,'Stale deletion cannot remove a newer draft');
reject(fn()=>LPM_Service::delete($id,$rev,$production+1),LPM_Conflict::class,'Stale production pointer prevents deletion');
check(LPM_Renderer::shortcode('price_table',['id'=>(string)$id])!=='','Rejected deletion leaves published output intact');
wp_set_current_user(0);
reject(fn()=>LPM_Service::delete($id,$rev,$production),RuntimeException::class,'Unauthenticated user cannot delete');
wp_set_current_user(1);
LPM_Service::$test_fault=static function($stage){if($stage==='before_transaction_commit'){throw new RuntimeException('test deletion rollback');}};
reject(fn()=>LPM_Service::delete($id,$rev,$production),RuntimeException::class,'Deletion transaction rolls back on injected failure');
LPM_Service::$test_fault=null;
check(LPM_Service::resource($id)['deleted_at']===null,'Failed deletion preserves active resource');
$result=LPM_Service::delete($id,$rev,$production);
check($result['deleted']&&$result['persisted'],'Deletion is independently verified after commit');
check(LPM_Renderer::shortcode('price_table',['id'=>(string)$id])==='','Deleted resource renders no public price');
reject(fn()=>LPM_Service::editor($id),InvalidArgumentException::class,'Deleted resource cannot be opened as an active editor');
reject(fn()=>LPM_Service::save($id,$content,$rev,$production),LPM_Conflict::class,'Old editor cannot recreate a deleted resource by saving');
$again=LPM_Service::delete($id,$rev,$production);
check($again['persisted'],'Deletion retry is idempotent');
global $wpdb;
$raw=LPM_Database::row($wpdb->prepare('SELECT * FROM '.LPM_Schema::table('resources').' WHERE id=%d',$id));
check(!empty($raw['deleted_at'])&&(int)$raw['production_version_id']===$production,'Soft deletion retains production pointer in database');
$versions=LPM_Database::scalar($wpdb->prepare('SELECT COUNT(*) FROM '.LPM_Schema::table('resource_versions').' WHERE resource_id=%d',$id));
check((int)$versions===count($before),'Soft deletion preserves immutable history');
$request=new WP_REST_Request('GET','/lpm/v1/resources');$request->set_param('type','price_table');$request->set_param('search',(string)$id);
$list=LPM_Admin::collection($request);
check(!in_array($id,array_map('intval',array_column($list['items'],'id')),true),'Deleted item is excluded from library API');
$catalog=LPM_Service::create('price_catalog','Catalog to delete');
check(LPM_Service::delete($catalog,0,0)['persisted'],'Unpublished catalog can be deleted without a production event');
echo 'SUCCESS '.$checks." simplified workflow and deletion checks on WordPress + MySQL.\n";
