<?php
require __DIR__.'/bootstrap.php';LPM_Schema::install();$checks=0;
function check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);echo 'PASS '.(++$checks).' '.$label."\n";}
function rejects($fn,$label){try{$fn();}catch(InvalidArgumentException $e){check(true,$label);return;}throw new RuntimeException($label);}
$data=LPM_Content::initial('price_table');$data['show_title']=false;$data['headers']=['Jenjang','Pintar','Cerdas','Genius'];
$data['presentation']=LPM_Content::presentation($data['headers'],'pricing');$data['presentation']['featured_column']=2;
$data['presentation']['columns']=[['subtitle'=>'Tingkat pendidikan','badge'=>''],['subtitle'=>'Paket hemat (4 sesi)','badge'=>'Starter'],['subtitle'=>'Paket intensif (8 sesi)','badge'=>'Populer'],['subtitle'=>'Paket lengkap (12 sesi)','badge'=>'Mastery']];
$levels=['TK / MENGAJI','KELAS 1–2 SD','KELAS 3–5 SD','KELAS 6 SD','KELAS 7–8 SMP','KELAS 9 SMP','KELAS 10–11 SMA','KELAS 12 SMA','UMUM','MAHASISWA'];$prices=[580000,600000,620000,640000,700000,740000,800000,940000,1200000,1200000];
$data['rows']=[];foreach($levels as $i=>$level){$row=[LPM_Content::cell($level)];for($n=1;$n<=3;$n++)$row[]=LPM_Content::cell(number_format($prices[$i]*$n,0,',','.'));$data['rows'][]=$row;}
$data['cta']=['label'=>'Konsultasi via WhatsApp','url'=>'https://wa.me/620000000000','new_tab'=>true];
$id=LPM_Service::create('price_table','Pricing reference fixture');$save=LPM_Service::save($id,$data,0,0);
check($save['persisted']&&LPM_Content::json(LPM_Service::editor($id)['content']['presentation'])===LPM_Content::json($data['presentation']),'Package annotations and footer persist in draft');
check(LPM_Renderer::shortcode('price_table',['id'=>(string)$id])==='','New design is private until publish');
$pub=LPM_Service::publish($id,1,0,'Pricing design fixture','pricing_'.bin2hex(random_bytes(16)));
$version=LPM_Service::version(LPM_Service::resource($id),$pub['version_id']);check((int)$version['snapshot_schema_version']===3,'Pricing snapshot declares schema 3');
$html=LPM_Renderer::shortcode('price_table',['id'=>(string)$id]);
check(str_contains($html,'lpm-pricing-card')&&str_contains($html,'Paket intensif (8 sesi)')&&str_contains($html,'Populer'),'Public renderer shows package subheadings and badges');
check(substr_count($html,'class="lpm-featured"')===11,'Featured header and all ten matching price cells highlighted');
check(substr_count($html,'class="lpm-currency"')===30,'All numeric prices get separate currency prefixes');
check(str_contains($html,'Konsultasikan jadwal')&&substr_count($html,'Konsultasi via WhatsApp')===1&&str_contains($html,'noopener noreferrer'),'Footer message and a single safe CTA render');
check(str_contains($html,'viewBox="0 0 24 24"'),'WhatsApp destination receives WhatsApp icon');
$data['presentation']['footer_title']='Footer baru';$data['rows'][0][1]['text']='Rp 590.000';$e=LPM_Service::editor($id);LPM_Service::save($id,$data,(int)$e['resource']['draft_revision'],$e['base']);
check(LPM_Renderer::shortcode('price_table',['id'=>(string)$id])===$html,'Editing presentation and prices cannot alter published snapshot before publish');
$r=LPM_Service::resource($id);$second=LPM_Service::publish($id,(int)$r['draft_revision'],(int)$r['production_version_id'],'Footer update fixture','pricing_'.bin2hex(random_bytes(16)));
$r=LPM_Service::resource($id);LPM_Service::publish($id,(int)$r['draft_revision'],(int)$r['production_version_id'],'Restore pricing fixture','pricing_'.bin2hex(random_bytes(16)),$pub['version_id']);
check(LPM_Renderer::shortcode('price_table',['id'=>(string)$id])===$html,'Restoring schema 3 recovers original presentation, prices and CTA exactly');
check(LPM_Service::snapshot(LPM_Service::version(LPM_Service::resource($id),$pub['version_id']))['presentation']['footer_title']!==$data['presentation']['footer_title'],'Original schema 3 snapshot stays immutable');
$e=LPM_Service::editor($id);LPM_Service::save($id,$data,(int)$e['resource']['draft_revision'],$e['base']);
$old=LPM_Content::initial('price_table');$old['rows']=[[LPM_Content::cell('Lama'),LPM_Content::cell('Rp100'),LPM_Content::cell('2 sesi')]];
check(LPM_Content::schema($old)===2&&!str_contains(LPM_Renderer::render('price_table',$old),'lpm-pricing-card'),'Existing schema 2 tables retain standard layout');
$horizontal=$data;$horizontal['orientation']='horizontal';check(!str_contains(LPM_Renderer::render('price_table',$horizontal),'lpm-pricing-card'),'Horizontal layout retains normal transpose rendering');
$bad=$data;$bad['presentation']['featured_column']=4;rejects(fn()=>LPM_Content::validate('price_table',$bad),'Out-of-range feature column rejected');
$bad=$data;$bad['presentation']['columns'][0]['subtitle']='<img src=x>';rejects(fn()=>LPM_Content::validate('price_table',$bad),'Package HTML rejected');
$bad=$data;$bad['presentation']['columns']=[];rejects(fn()=>LPM_Content::validate('price_table',$bad),'Mismatched package metadata rejected');
$bad=$data;$bad['presentation']=null;rejects(fn()=>LPM_Content::validate('price_table',$bad),'Null presentation rejected');
$bad=$data;$bad['cta']['url']='javascript:alert(1)';rejects(fn()=>LPM_Content::validate('price_table',$bad),'Executable footer URL rejected');
$empty=$data;$empty['cta']=LPM_Content::cta();$empty['presentation']['footer_note']='';$empty['presentation']['footer_title']='';check(!str_contains(LPM_Renderer::render('price_table',$empty),'<footer'),'Entirely empty footer is omitted');
file_put_contents(dirname(__DIR__).'/artifacts/pricing-reference.html','<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public.css"><style>body{margin:28px 36px;background:#f7f9fc;font-family:Arial,sans-serif}@media(max-width:700px){body{margin:16px}}</style>'.$html.'</html>');
file_put_contents(__DIR__.'/runtime/pricing-fixture.json',wp_json_encode(['id'=>$id]));
echo 'SUCCESS '.$checks." pricing design checks on WordPress + MySQL.\n";
