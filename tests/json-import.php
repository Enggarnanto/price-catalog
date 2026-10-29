<?php
require __DIR__.'/bootstrap.php';LPM_Schema::install();$checks=0;
function check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);echo 'PASS '.(++$checks).' '.$label."\n";}
function rejects($fn,$label){try{$fn();}catch(InvalidArgumentException|JsonException|LPM_Conflict $e){check(true,$label);return;}throw new RuntimeException($label);}
function key_import(){return 'import_'.bin2hex(random_bytes(16));}
function doc_import(array $items,string $type='price_table'){return wp_json_encode(['format'=>'lpm-export','version'=>1,'resource_type'=>$type,'resources'=>$items]);}
$source=file_get_contents(dirname(__DIR__).'/PRICELIST_GALIH/tabel-price-semua-published-2026-10-29T08-37-07-642Z.json');$records=LPM_JSON_Import::parse($source);
check(count($records)===18,'Actual user export parses all 18 program tables');
check($records[0]['metadata']['description']!==''&&$records[0]['content']['description']!==''&&$records[0]['content']['show_description']===false,'Descriptions and original visibility are preserved');
$original=json_decode($source,true);foreach($records as $i=>$record)check($record['content']==$original['resources'][$i]['content'],'Original prices and formatting preserved for source #'.$record['source_id']);
check(count(LPM_JSON_Import::parse("\xEF\xBB\xBF".$source))===18,'UTF-8 BOM is accepted');
$single=$original['resources'][0];$backup=wp_json_encode(['resource_id'=>$single['resource_id'],'metadata'=>$single['metadata'],'content'=>$single['content']]);check(count(LPM_JSON_Import::parse($backup))===1,'Single local backup JSON is supported');
rejects(fn()=>LPM_JSON_Import::parse('{broken'),'Malformed JSON rejected');rejects(fn()=>LPM_JSON_Import::parse(str_repeat(' ',LPM_JSON_Import::MAX_BYTES+1)),'Oversized JSON rejected');
rejects(fn()=>LPM_JSON_Import::parse(doc_import([])),'Empty export rejected');rejects(fn()=>LPM_JSON_Import::parse(doc_import([$single,$single])),'Duplicate source IDs rejected');
$bad=$single;$bad['metadata']['description']='<script>alert(1)</script>';rejects(fn()=>LPM_JSON_Import::parse(doc_import([$bad])),'Markup in imported descriptions rejected');
$bad=$single;$bad['content']['rows'][0][1]['url']='javascript:alert(1)';rejects(fn()=>LPM_JSON_Import::parse(doc_import([$bad])),'Executable cell URL rejected');
$bad=json_decode(doc_import([$single]),true);$bad['version']=2;rejects(fn()=>LPM_JSON_Import::parse(wp_json_encode($bad)),'Unsupported export version rejected');
$table=LPM_Schema::table('resources');global $wpdb;$count=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table");$plan=LPM_JSON_Import::preview(doc_import([$single]),'new');$item=$plan['items'][0];
check((int)$wpdb->get_var("SELECT COUNT(*) FROM $table")===$count,'Preview creates no resources');$op=key_import();$result=LPM_JSON_Import::apply($item,$item['preview_token'],$op);$id=$result['id'];
check($result['persisted']&&(int)$wpdb->get_var("SELECT COUNT(*) FROM $table")===$count+1,'Create mode commits a verified resource draft');
check(LPM_Service::editor($id)['content']==$single['content']&&LPM_Renderer::shortcode('price_table',['id'=>(string)$id])==='','New import preserves exact content and remains private');
$again=LPM_JSON_Import::apply($item,$item['preview_token'],$op);check($again['id']===$id&&$again['replayed']&&(int)$wpdb->get_var("SELECT COUNT(*) FROM $table")===$count+1,'Retry of same import does not create duplicate resources');
$tampered=$item;$tampered['record']['content']['title']='Different';rejects(fn()=>LPM_JSON_Import::apply($tampered,$item['preview_token'],key_import()),'Data changed after preview is rejected');
$changed=$single;$changed['content']['title']='Different';$other=LPM_JSON_Import::preview(doc_import([$changed]),'new')['items'][0];rejects(fn()=>LPM_JSON_Import::apply($other,$other['preview_token'],$op),'Same operation key cannot import a different payload');
$ed=LPM_Service::editor($id);$published=LPM_Service::publish($id,$ed['resource']['draft_revision'],0,'Import fixture','publish_'.bin2hex(random_bytes(12)));$html=LPM_Renderer::shortcode('price_table',['id'=>(string)$id]);
$changed=$single;$changed['resource_id']=$id;$changed['metadata']['description']='Deskripsi internal dari JSON';$changed['content']['description']='Deskripsi baru sesuai program';$changed['content']['rows'][0][1]['text']='123456';
$plan=LPM_JSON_Import::preview(doc_import([$changed]),'existing');$item=$plan['items'][0];$r=LPM_JSON_Import::apply($item,$item['preview_token'],key_import());
check($r['id']===$id&&LPM_Service::editor($id)['content']==$changed['content'],'Existing mode updates exact draft at the same ID');
check(LPM_Renderer::shortcode('price_table',['id'=>(string)$id])===$html&&count(LPM_Service::history($id))===1,'Existing import cannot change production or immutable history');
check(LPM_Service::editor($id)['resource']['description']==='Deskripsi internal dari JSON','Internal metadata is imported');
$stale=LPM_JSON_Import::preview(doc_import([$changed]),'existing')['items'][0];$ed=LPM_Service::editor($id);$local=$ed['content'];$local['title']='Concurrent edit';LPM_Service::save($id,$local,$ed['resource']['draft_revision'],$ed['base']);
rejects(fn()=>LPM_JSON_Import::apply($stale,$stale['preview_token'],key_import()),'Stale preview cannot overwrite a concurrent draft');
$missing=$changed;$missing['resource_id']=99999999;$missing=LPM_JSON_Import::preview(doc_import([$missing]),'existing')['items'][0];check($missing['error']!==''&&$missing['preview_token']==='','Missing target is reported and cannot be imported');
$faultItem=LPM_JSON_Import::preview(doc_import([$single]),'new')['items'][0];$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table");$faultKey=key_import();LPM_Service::$test_fault=static function($stage){if($stage==='before_transaction_commit')throw new RuntimeException('Import rollback fixture');};
try{LPM_JSON_Import::apply($faultItem,$faultItem['preview_token'],$faultKey);throw new LogicException('No injected failure');}catch(RuntimeException $e){check($e->getMessage()==='Import rollback fixture','Before-commit import failure injected');}finally{LPM_Service::$test_fault=null;}
check((int)$wpdb->get_var("SELECT COUNT(*) FROM $table")===$before,'Failed create rolls back resource and receipt without orphan');
$faultKey=key_import();LPM_Service::$test_fault=static function($stage){if($stage==='after_transaction_commit')throw new RuntimeException('Import lost response');};
try{LPM_JSON_Import::apply($faultItem,$faultItem['preview_token'],$faultKey);throw new LogicException('No injected failure');}catch(RuntimeException $e){check($e->getMessage()==='Import lost response','Lost-response import failure injected');}finally{LPM_Service::$test_fault=null;}
$recovered=LPM_JSON_Import::apply($faultItem,$faultItem['preview_token'],$faultKey);check($recovered['replayed']&&(int)$wpdb->get_var("SELECT COUNT(*) FROM $table")===$before+1,'Retry confirms committed import exactly once after lost response');
$c=LPM_Content::initial('price_catalog');$c['items']=[['title'=>'','price'=>'','description'=>'','attachment_id'=>99999999,'image_url'=>'https://example.com/catalog.png','alt'=>'Harga','caption'=>'','link'=>'','new_tab'=>false,'cta'=>LPM_Content::cta()]];
$catalog=['resource_id'=>777,'metadata'=>['name'=>'JSON catalog fixture','description'=>'Katalog import'],'content'=>$c];$cp=LPM_JSON_Import::preview(doc_import([$catalog],'price_catalog'),'new')['items'][0];
check($cp['record']['content']['items'][0]['attachment_id']===0&&count($cp['record']['warnings'])===1,'Foreign media IDs are cleared and reported before import');
$cr=LPM_JSON_Import::apply($cp,$cp['preview_token'],key_import());check($cr['persisted']&&LPM_Service::editor($cr['id'])['content']['items'][0]['image_url']===$c['items'][0]['image_url'],'Catalog JSON imports as draft with URL metadata intact');
$wrong=$changed;$wrong['resource_id']=$cr['id'];check(LPM_JSON_Import::preview(doc_import([$wrong]),'existing')['items'][0]['error']!=='','Existing table cannot overwrite a catalog');
$author=wp_insert_user(['user_login'=>'json_author_'.bin2hex(random_bytes(4)),'user_pass'=>bin2hex(random_bytes(12)),'role'=>'author']);wp_set_current_user($author);$request=new WP_REST_Request('POST','/lpm/v1/json/preview');$request->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));check(is_wp_error(LPM_Admin::permission($request)),'Writer cannot access JSON import endpoints');wp_set_current_user(1);
$race=LPM_JSON_Import::preview(doc_import([$single]),'new')['items'][0];file_put_contents(__DIR__.'/runtime/json-import-worker.json',wp_json_encode(['item'=>$race,'operation_key'=>key_import()]));$start=(string)(microtime(true)+2);$workers=[];$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table");
for($i=0;$i<2;$i++){$process=proc_open([PHP_BINARY,__DIR__.'/json-import-worker.php',$start],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Cannot start import worker');fclose($pipes[0]);$workers[]=[$process,$pipes];}
$receipts=[];foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException($out.$err);$receipts[]=json_decode($out,true,32,JSON_THROW_ON_ERROR);}
check($receipts[0]['id']===$receipts[1]['id'],'Concurrent retry returns the same committed ID on independent connections');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM $table")===$before+1,'Concurrent import creates only one resource');
file_put_contents(__DIR__.'/runtime/json-import-fixture.json',wp_json_encode(['id'=>$id,'catalog'=>$cr['id'],'existing'=>$changed,'single'=>$single]));echo 'SUCCESS '.$checks." JSON import server checks\n";
