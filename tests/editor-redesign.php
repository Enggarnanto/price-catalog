<?php
require __DIR__.'/bootstrap.php';LPM_Schema::install();$checks=0;
function check($ok,$text){global $checks;if(!$ok)throw new RuntimeException($text);echo 'PASS '.(++$checks).' '.$text."\n";}
$d=LPM_Content::initial('price_table');$d['title']='';$d['show_title']=false;$d['headers']=['Jenjang Pendidikan','Paket Pintar','Paket Cerdas','Paket Genius'];
$d['presentation']=LPM_Content::presentation($d['headers'],'pricing');$d['presentation']['featured_column']=1;$d['presentation']['columns'][1]['badge']='Favorit';
$d['table_options']=LPM_Money::defaults($d['headers']);$d['table_options']['price_columns']=[1,2,3];
$d['cta']=['label'=>'Konsultasi Sekarang','url'=>'https://example.com/konsultasi','new_tab'=>true];
$names=['TK / Mengaji','Kelas 1–2 SD','Kelas 3–5 SD','Kelas 6 SD','Kelas 7–8 SMP','Kelas 9 SMP','Kelas 10–11 SMA','Kelas 12 SMA','Umum','Mahasiswa'];$prices=[580000,600000,620000,640000,700000,740000,800000,940000,1200000,1200000];$d['rows']=[];
foreach($names as $n=>$name)$d['rows'][]=[LPM_Content::cell($name),LPM_Content::cell((string)$prices[$n]),LPM_Content::cell((string)($prices[$n]*2)),LPM_Content::cell((string)($prices[$n]*3))];
$id=LPM_Service::create('price_table','Harga Les Privat Nasional – Pintar, Cerdas, Genius');$saved=LPM_Service::save($id,$d,0,0);LPM_Service::publish($id,$saved['revision'],0,'Editor reference fixture','editor_'.bin2hex(random_bytes(12)));
$before=LPM_Service::resource($id);$request=new WP_REST_Request('POST','/lpm/v1/preview');$request->set_param('type','price_table');$draft=$d;$draft['rows'][0][1]['text']='599000';$request->set_param('content',$draft);
$request->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));
check(LPM_Admin::permission($request)===true,'Administrator may render an unsaved preview');$preview=LPM_Admin::preview($request);
check(str_contains($preview['html'],'Rp 599.000'),'Preview uses server renderer on unsaved content');
check(LPM_Service::resource($id)===$before,'Live preview never writes draft, revision or production');
$author=wp_insert_user(['user_login'=>'preview_'.bin2hex(random_bytes(4)),'user_pass'=>bin2hex(random_bytes(12)),'role'=>'author']);wp_set_current_user($author);$request->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));check(is_wp_error(LPM_Admin::permission($request)),'Writers cannot preview arbitrary private drafts');wp_set_current_user(1);
$upload=wp_upload_bits('catalog-editor-fixture.png',null,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7ioAAAAASUVORK5CYII='));
if(function_exists('imagecreatetruecolor')){$im=imagecreatetruecolor(640,400);$bg=imagecolorallocate($im,236,242,255);imagefill($im,0,0,$bg);$blue=imagecolorallocate($im,13,57,142);$white=imagecolorallocate($im,255,255,255);imagefilledrectangle($im,0,0,640,90,$blue);imagestring($im,5,36,35,'KATALOG LES PRIVAT 2026',$white);imagestring($im,5,36,140,'PAKET PINTAR',$blue);imagestring($im,5,36,190,'Rp 580.000 / paket',$blue);imagestring($im,4,36,260,'Belajar fleksibel bersama tutor pilihan',$blue);imagepng($im,$upload['file']);imagedestroy($im);}
$image=wp_insert_attachment(['post_title'=>'Catalog editor artwork','post_mime_type'=>'image/png','post_status'=>'inherit'],$upload['file']);
$c=LPM_Content::initial('price_catalog');$c['mode']='carousel';$c['items']=[];for($n=0;$n<2;$n++)$c['items'][]=['title'=>'','price'=>'','description'=>'','attachment_id'=>$image,'image_url'=>$upload['url'],'alt'=>'Paket '.($n+1),'caption'=>'Katalog harga '.($n+1),'link'=>'https://example.com/paket','new_tab'=>false,'cta'=>['label'=>'Konsultasi','url'=>'https://example.com/konsultasi','new_tab'=>false]];
$cid=LPM_Service::create('price_catalog','Katalog Les Privat Nasional');$saved=LPM_Service::save($cid,$c,0,0);LPM_Service::publish($cid,$saved['revision'],0,'Catalog editor fixture','editor_'.bin2hex(random_bytes(12)));
$request->set_param('type','price_catalog');$request->set_param('content',$c);check(str_contains(LPM_Admin::preview($request)['html'],'data-lpm-carousel'),'Catalog live preview includes carousel renderer');
file_put_contents(__DIR__.'/runtime/editor-fixture.json',wp_json_encode(['table'=>$id,'catalog'=>$cid]));echo 'SUCCESS '.$checks." server preview checks\n";
