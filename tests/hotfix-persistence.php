<?php
require __DIR__.'/bootstrap.php';
$checks=0;
function okfix(bool $v,string $message):void{global $checks;if(!$v){throw new RuntimeException('FAIL '.$message);}echo 'PASS '.(++$checks).' '.$message."\n";}
function rejectfix(callable $fn,string $message):void{try{$fn();}catch(LPM_Persistence_Error $e){okfix(true,$message);return;}okfix(false,$message);}
LPM_Schema::install();
$id=LPM_Service::create('price_table','Latiseducation Nasional - synthetic persistence test');
$data=LPM_Content::initial('price_table');$data['title']='Latiseducation Nasional - test title';$data['headers']=['Jenjang','Reguler','Eksklusif','Privat'];$data['rows']=[];
for($i=1;$i<=10;$i++){$data['rows'][]=[LPM_Content::cell('Jenjang '.$i),LPM_Content::cell('Rp'.(100000+$i)),LPM_Content::cell('Rp'.(200000+$i)),LPM_Content::cell('Rp'.(300000+$i))];}
$fault=static function($stage){if($stage==='before_transaction_commit'){mysqli_rollback(LPM_Database::connection());}};
LPM_Service::$test_fault=$fault;
rejectfix(fn()=>LPM_Service::save($id,$data,0,0),'Save cannot acknowledge a rolled-back transaction as success');
LPM_Service::$test_fault=null;
okfix((int)LPM_Service::resource($id)['draft_revision']===0,'Failed save leaves persistent resource unchanged');
$saved=LPM_Service::save($id,$data,0,0);
okfix($saved['persisted']===true&&$saved['revision']===1,'Save succeeds only with independent committed-state verification');
$reader=LPM_Database::open();$row=LPM_Database::row($wpdb->prepare('SELECT * FROM '.LPM_Schema::table('resources').' WHERE id=%d',$id),$reader);mysqli_close($reader);
okfix(LPM_Content::json(LPM_Content::decode($row['draft_data']))===LPM_Content::json($data),'Independent connection reads all 10 levels and 30 prices plus title');
LPM_Service::$test_fault=$fault;
rejectfix(fn()=>LPM_Service::publish($id,1,0,'Injected rollback','fix_'.bin2hex(random_bytes(15))),'Publish cannot acknowledge an empty commit as V1 success');
LPM_Service::$test_fault=null;
okfix(LPM_Service::resource($id)['production_version_id']===null&&LPM_Service::history($id)===[],'Rejected publication has no phantom production/history');
$key='fix_'.bin2hex(random_bytes(15));$published=LPM_Service::publish($id,1,0,'Verified real publication',$key);
okfix($published['persisted']===true&&$published['version_number']===1,'Corrected publication creates verified V1');
okfix(count(LPM_Service::history($id))===1&&LPM_Service::resource($id)['production_version_id']==$published['version_id'],'Production pointer and history persist together');
$snapshot=LPM_Service::snapshot(LPM_Service::version(LPM_Service::resource($id),$published['version_id']));
okfix(LPM_Content::json($snapshot)===LPM_Content::json($data),'Published snapshot retains every fixture value');
// A shared WordPress transaction must not own or roll back plugin writes.
mysqli_begin_transaction($wpdb->dbh);$data['title']='Persist after unrelated WordPress rollback';LPM_Service::save($id,$data,2,$published['version_id']);mysqli_rollback($wpdb->dbh);
okfix(LPM_Service::editor($id)['content']['title']===$data['title'],'Rollback on shared wpdb connection cannot undo a saved draft');
$data['rows'][0][1]['text']='Rp123.456 - diskon 10%';$data['description']='Harga 100% tersimpan';
$r=LPM_Service::resource($id);$saved=LPM_Service::save($id,$data,(int)$r['draft_revision'],$published['version_id']);
okfix(LPM_Service::editor($id)['content']['rows'][0][1]['text']===$data['rows'][0][1]['text'],'Literal percent signs survive native SQL without placeholder corruption');
// Simulate a query-cache/drop-in returning old data on the shared wpdb path.
$table=LPM_Schema::table('resources');$old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id),ARRAY_A);
$stale=static function($sql)use($table,$id,$old,$wpdb){
 if(str_starts_with($sql,'SELECT * FROM '.$table.' WHERE id = '.$id)){
  $parts=[];foreach($old as $name=>$value){$parts[]=($value===null?'NULL':$wpdb->prepare('%s',$value)).' AS `'.$name.'`';}return $wpdb->remove_placeholder_escape('SELECT '.implode(',',$parts));
 }return $sql;
};
add_filter('query',$stale,20);$data['title']='Fresh committed title';$r=LPM_Service::resource($id);LPM_Service::save($id,$data,(int)$r['draft_revision'],$published['version_id']);
$cached=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d",$id),ARRAY_A);
okfix((int)$cached['draft_revision']<(int)LPM_Service::resource($id)['draft_revision'],'Fixture reproduces stale shared-wpdb reads while primary state remains current');
okfix(LPM_Service::editor($id)['content']['title']==='Fresh committed title','Editor reads committed draft rather than stale query-cache result');remove_filter('query',$stale,20);
// Response lost after commit: idempotent retry must verify existing version, not duplicate it.
$r=LPM_Service::resource($id);$revision=(int)$r['draft_revision'];$retry='fix_'.bin2hex(random_bytes(15));
LPM_Service::$test_fault=static function($stage){if($stage==='after_transaction_commit'){throw new LPM_Persistence_Error('Injected lost verification response.');}};
rejectfix(fn()=>LPM_Service::publish($id,$revision,$published['version_id'],'Lost response after commit',$retry),'Unconfirmed response does not produce a success acknowledgement');
LPM_Service::$test_fault=null;$replayed=LPM_Service::publish($id,$revision,$published['version_id'],'Lost response after commit',$retry);
okfix($replayed['replayed']&&$replayed['persisted']&&count(LPM_Service::history($id))===2,'Retry confirms committed V2 exactly once');
// REST must expose an explicit persistence failure, never HTTP 200 with success text.
do_action('rest_api_init');$r=LPM_Service::resource($id);$request=new WP_REST_Request('POST','/lpm/v1/resources/'.$id.'/save');$request->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));
foreach(['content'=>$data,'revision'=>(int)$r['draft_revision'],'base'=>(int)$r['production_version_id']] as $k=>$v){$request->set_param($k,$v);}
LPM_Service::$test_fault=$fault;$response=rest_do_request($request);LPM_Service::$test_fault=null;
okfix($response->get_status()===503&&$response->get_data()['code']==='lpm_persistence_unverified','REST save returns 503 when commit durability cannot be verified');
echo "SUCCESS: $checks persistence hotfix checks passed.\n";
