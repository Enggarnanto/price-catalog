<?php
require __DIR__.'/bootstrap.php';LPM_Schema::install();$checks=0;
function check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);echo 'PASS '.(++$checks).' '.$label."\n";}
function rejects($fn,$label){try{$fn();}catch(InvalidArgumentException|LPM_Conflict $e){check(true,$label);return;}throw new RuntimeException($label);}
$d=LPM_Content::initial('price_table');$d['headers']=['Paket','Harga','Keterangan'];$d['title']='Harga fitur baru';$d['rows']=[[LPM_Content::cell('Paket A'),LPM_Content::cell('150000'),LPM_Content::cell('4 sesi')],[LPM_Content::cell('Paket B'),LPM_Content::cell('250000'),LPM_Content::cell('8 sesi')]];
for($i=3;$i<=8;$i++)$d['rows'][]=[LPM_Content::cell('Paket '.$i),LPM_Content::cell((string)($i*150000)),LPM_Content::cell($i.' sesi')];
$d['table_options']=LPM_Money::defaults($d['headers']);$d['table_options']['mobile_orientation']='horizontal';
check(LPM_Content::schema($d)===5&&LPM_Content::validate('price_table',$d)==$d,'Schema 5 validates currency and mobile settings');
$o=$d['table_options'];check(LPM_Money::format('150000',$o)==='Rp 150.000','Raw IDR nominal groups correctly');
$o['decimals']=2;check(LPM_Money::format('150.000,50',$o)==='Rp 150.000,50','Indonesian groups and decimals are parsed explicitly');
$o['currency']='USD';$o['number_format']='en-US';check(LPM_Money::format('1,234.50',$o)==='US$ 1,234.50','USD supports international number format');
foreach(LPM_Money::CURRENCIES as $code=>$symbol){$o['currency']=$code;check(str_starts_with(LPM_Money::format('1000',$o),$symbol.' '),$code.' currency symbol supported');}
check(LPM_Money::format('Hubungi kami',$o)==='Hubungi kami'&&LPM_Money::format('4 sesi',$o)==='4 sesi','Mixed descriptive text stays intact');
$bad=$d;$bad['table_options']['price_columns']=[4];rejects(fn()=>LPM_Content::validate('price_table',$bad),'Out-of-range nominal column rejected');
$bad=$d;$bad['table_options']['currency']='XXX';rejects(fn()=>LPM_Content::validate('price_table',$bad),'Unsupported currency rejected');
$bad=$d;$bad['table_options']['freeze_first_column']='true';rejects(fn()=>LPM_Content::validate('price_table',$bad),'Sticky flag requires a boolean');
$html=LPM_Renderer::render('price_table',$d);check(str_contains($html,'Rp 150.000')&&str_contains($html,'4 sesi')&&!str_contains($html,'Rp 4 sesi'),'Only selected columns receive money formatting');
check(str_contains($html,'lpm-desktop-view')&&str_contains($html,'lpm-mobile-view')&&str_contains($html,'data-orientation="horizontal"'),'Desktop/mobile orientations render independently');
check(substr_count($html,'lpm-freeze-first')===2,'Both table orientations enable sticky first column');
$id=LPM_Service::create('price_table','Bulk currency mobile fixture');$s=LPM_Service::save($id,$d,0,0);$p=LPM_Service::publish($id,$s['revision'],0,'Initial fixture','features_'.bin2hex(random_bytes(12)));
$firstVersion=$p['version_id'];$published=LPM_Renderer::shortcode('price_table',['id'=>(string)$id]);$r=LPM_Service::resource($id);
$draft=LPM_Service::status($id,(int)$r['draft_revision'],(int)$r['production_version_id'],'draft');
check($draft['persisted']&&$draft['changed']&&LPM_Service::editor($id)['content']==$d,'Draft action creates an exact editable published copy');
check(LPM_Renderer::shortcode('price_table',['id'=>(string)$id])===$published,'Preparing a draft leaves published HTML unchanged');
$same=LPM_Service::status($id,$draft['revision'],$draft['production'],'draft');check(!$same['changed']&&$same['revision']==$draft['revision'],'Draft action preserves existing draft without revision churn');
rejects(fn()=>LPM_Service::status($id,0,$draft['production'],'unpublish'),'Stale selection cannot change publication status');
$before=LPM_Service::resource($id);LPM_Service::$test_fault=static function($stage){if($stage==='before_transaction_commit')throw new RuntimeException('Injected rollback');};
try{LPM_Service::status($id,$draft['revision'],$draft['production'],'unpublish');throw new RuntimeException('Missing failure');}catch(RuntimeException $e){check($e->getMessage()==='Injected rollback','Unpublish fault occurs before commit');}finally{LPM_Service::$test_fault=null;}
check(LPM_Service::resource($id)===$before,'Failed unpublish rolls back publication and draft atomically');
$unpub=LPM_Service::status($id,$draft['revision'],$draft['production'],'unpublish');
check($unpub['persisted']&&LPM_Renderer::shortcode('price_table',['id'=>(string)$id])==='','Unpublish withdraws the public shortcode');
$e=LPM_Service::editor($id);check($e['content']==$d&&$e['base']===0&&!$e['stale'],'Unpublish retains draft and resets its publication base');
check(LPM_Service::snapshot(LPM_Service::version($e['resource'],$firstVersion))==$d,'Unpublish preserves immutable version history');
global $wpdb;$events=$wpdb->get_results($wpdb->prepare('SELECT id,event_type FROM '.LPM_Schema::table('cache_events').' WHERE version_id=%d',$firstVersion),ARRAY_A);
check(count($events)===2&&count(array_unique(array_column($events,'id')))===2,'Unpublish gets a distinct durable cache event from publish');
$noop=LPM_Service::status($id,$unpub['revision'],0,'unpublish');check(!$noop['changed'],'Already unpublished resource is a safe no-op');
$again=LPM_Service::publish($id,$e['resource']['draft_revision'],0,'Republish fixture','features_'.bin2hex(random_bytes(12)));
check($again['version_id']!==$firstVersion&&LPM_Renderer::shortcode('price_table',['id'=>(string)$id])===$published,'Republish restores formatted output as a new version');
$new=LPM_Service::create('price_table','Bulk empty draft fixture');$empty=LPM_Service::status($new,0,0,'draft');check($empty['persisted']&&LPM_Service::editor($new)['content']['title']==='Bulk empty draft fixture','New empty resource can receive a draft');
$e=LPM_Service::editor($id);$d2=$d;$d2['rows'][0][1]['text']='175000';$saved=LPM_Service::save($id,$d2,(int)$e['resource']['draft_revision'],$e['base']);
LPM_Service::status($id,$saved['revision'],$again['version_id'],'draft');check(LPM_Service::editor($id)['content']==$d2,'Draft action never replaces existing unsent changes');
$cat=LPM_Service::create('price_catalog','Bulk catalog fixture');$s=LPM_Service::status($cat,0,0,'draft');check($s['persisted']&&LPM_Service::editor($cat)['content']['mode']==='single','Bulk draft also supports catalogs');
file_put_contents(__DIR__.'/runtime/features-fixture.json',wp_json_encode(['table'=>$id,'empty'=>$new,'catalog'=>$cat]));
file_put_contents(dirname(__DIR__).'/artifacts/features-public.html','<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/price-catalogs/assets/public.css"><style>body{margin:16px;font:15px Arial}main{max-width:1000px;margin:auto}</style><main>'.$published.'</main></html>');
$reverse=$d;$reverse['orientation']='horizontal';$reverse['table_options']['mobile_orientation']='vertical';$reverse['headers']=array_merge($reverse['headers'],['Jadwal','Catatan']);foreach($reverse['rows'] as &$row){$row[]=LPM_Content::cell('Senin');$row[]=LPM_Content::cell('Online');}unset($row);
file_put_contents(dirname(__DIR__).'/artifacts/features-reverse.html','<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/price-catalogs/assets/public.css"><style>body{margin:16px;font:15px Arial}</style>'.LPM_Renderer::render('price_table',$reverse).'</html>');
echo 'SUCCESS '.$checks." WordPress/database feature checks\n";
