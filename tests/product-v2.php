<?php
require __DIR__.'/bootstrap.php';
$checks=0;
function check(bool $v,string $m):void{global $checks;if(!$v){throw new RuntimeException('FAIL '.$m);}echo 'PASS '.(++$checks).' '.$m."\n";}
function rejects(callable $fn,string $m):void{try{$fn();}catch(InvalidArgumentException|LPM_Conflict $e){check(true,$m);return;}check(false,$m);}
function key2():string{return 'p2_'.bin2hex(random_bytes(15));}
function save2(int $id,array $data,?array $meta=null):array{$e=LPM_Service::editor($id);return LPM_Service::save($id,$data,(int)$e['resource']['draft_revision'],$e['base'],false,null,$meta);}
function pub2(int $id):array{$r=LPM_Service::resource($id);return LPM_Service::publish($id,(int)$r['draft_revision'],(int)$r['production_version_id'],'Product v2 test',key2());}
LPM_Schema::install();LPM_Usage::boot();
$id=LPM_Service::create('price_table','Grid '.key2());$data=LPM_Content::initial('price_table');$data['title']='Biaya Privat';$data['headers']=['Paket','Harga','Pertemuan'];
$data['rows']=[[LPM_Content::cell('Reguler'),LPM_Content::cell('Rp150.000','https://example.com/reguler',true),LPM_Content::cell('8')],[LPM_Content::cell('Eksklusif'),LPM_Content::cell('Rp250.000'),LPM_Content::cell('12')]];
$saved=save2($id,$data,['name'=>'Grid tersimpan','description'=>'Deskripsi internal']);
check($saved['content']==LPM_Service::editor($id)['content'],'Save returns the actual persisted draft');
check(LPM_Content::json(LPM_Service::editor($id)['content']['rows'])===LPM_Content::json($data['rows']),'Exit and reopen preserves every grid cell and link');
check(LPM_Service::resource($id)['name']==='Grid tersimpan','Internal metadata persists with draft');
$data['rows'][0][1]['text']='Rp175.000';save2($id,$data);check(LPM_Service::editor($id)['content']['rows'][0][1]['text']==='Rp175.000','Existing draft updates and reopens');
$v1=pub2($id);check(LPM_Service::version(LPM_Service::resource($id),$v1['version_id'])['snapshot_schema_version']==='2','V2 snapshot stores schema version 2');
$vertical=LPM_Renderer::shortcode('price_table',['id'=>(string)$id]);check(str_contains($vertical,'data-orientation="vertical"')&&str_contains($vertical,'noopener noreferrer'),'Vertical table and cell links render');
$data['orientation']='horizontal';save2($id,$data);check(LPM_Renderer::shortcode('price_table',['id'=>(string)$id])===$vertical,'Orientation draft does not affect production');pub2($id);
$horizontal=LPM_Renderer::shortcode('price_table',['id'=>(string)$id]);check(str_contains($horizontal,'data-orientation="horizontal"')&&preg_match('#<thead>.*Reguler.*Eksklusif.*</thead>#s',$horizontal)===1,'Horizontal mode transposes packages into columns');
check(strpos($horizontal,'Rp175.000')<strpos($horizontal,'Rp250.000'),'Horizontal transpose preserves cell positions');
$a=wp_insert_post(['post_type'=>'post','post_title'=>'Article A','post_status'=>'publish','post_author'=>1,'post_content'=>'[latis_price_table id="'.$id.'"]']);
$b=wp_insert_post(['post_type'=>'page','post_title'=>'Article B','post_status'=>'publish','post_author'=>1,'post_content'=>"[latis_price_table id='$id']"]);
$beforeA=get_post_field('post_content',$a);$beforeB=get_post_field('post_content',$b);$data['rows'][0][1]['text']='Rp190.000';save2($id,$data);pub2($id);
check(str_contains(do_shortcode($beforeA),'Rp190.000')&&str_contains(do_shortcode($beforeB),'Rp190.000'),'One price update changes both article renders');
check(get_post_field('post_content',$a)===$beforeA&&get_post_field('post_content',$b)===$beforeB,'Both articles retain unchanged shortcode content');
$used=LPM_Usage::posts($id,'price_table');check($used['total']===2,'Usage index counts unique posts/pages');
$scan=LPM_Usage::scan("[latis_price_table id=$id] [latis_price_table id='$id'] [[latis_price_catalog id=999999]]");check(count($scan['resources'])===1,'Usage parser supports unquoted IDs, deduplicates and excludes escaped shortcode');
$block=wp_insert_post(['post_type'=>'wp_block','post_title'=>'Synced price','post_status'=>'publish','post_author'=>1,'post_content'=>'[latis_price_table id="'.$id.'"]']);
$parent=wp_insert_post(['post_type'=>'post','post_title'=>'Synced owner','post_status'=>'publish','post_author'=>1,'post_content'=>'<!-- wp:block {"ref":'.$block.'} /-->']);
check(LPM_Usage::posts($id,'price_table')['total']===3,'Synced block references count on parent articles');
wp_update_post(['ID'=>$block,'post_content'=>'No shortcode']);check(LPM_Usage::posts($id,'price_table')['total']===2,'Editing synced block refreshes dependent articles');
wp_update_post(['ID'=>$block,'post_content'=>'<!-- wp:block {"ref":'.$block.'} /-->']);$cyclic=LPM_Usage::scan('<!-- wp:block {"ref":'.$block.'} /-->');check((bool)array_filter($cyclic['refs']),'Synced block cycles are detected without recursion failure');
wp_trash_post($a);check(LPM_Usage::posts($id,'price_table')['total']===1,'Trashed post removed from usage');wp_untrash_post($a);check(LPM_Usage::posts($id,'price_table')['total']===2,'Restored post returns to usage');wp_delete_post($a,true);check(LPM_Usage::posts($id,'price_table')['total']===1,'Deleted post removed from usage');
$s=LPM_Usage::step(true);while(!$s['complete']){$s=LPM_Usage::step();}check($s['complete'],'Batch rebuild reaches complete status');
$parsed=LPM_CSV::parse("\xEF\xBB\xBFPaket;Harga;Catatan\nReguler;150000;\"Sesi; privat\nMultiline\"",';');
check($parsed['count']===1&&$parsed['rows'][0][2]['text']==="Sesi; privat\nMultiline",'CSV handles BOM, semicolon, quoted delimiter and newline');
$csv=LPM_CSV::parse("Paket,Harga\nReguler,150000\nEksklusif,250000");check($csv['columns']===2&&$csv['count']===2,'CSV comma parser returns matrix');
check(LPM_CSV::parse("A;B",';',false)['headers']===['Kolom 1','Kolom 2'],'CSV can generate headers');
rejects(fn()=>LPM_CSV::parse("A,B\n1,2,3"),'Inconsistent CSV columns rejected');rejects(fn()=>LPM_CSV::parse("A,B\n\"unfinished,1"),'Unclosed CSV quote rejected');
rejects(fn()=>LPM_CSV::parse("A,B\n<script>,1"),'CSV markup is not executable');rejects(fn()=>LPM_CSV::parse(str_repeat('a',2097153)),'Oversized CSV rejected');
$original=LPM_Service::resource($id);$current=LPM_Service::editor($id)['content'];$import=array_replace($current,['headers'=>$csv['headers'],'rows'=>$csv['rows']]);save2($id,$import);
check(LPM_Service::resource($id)['production_version_id']===$original['production_version_id'],'CSV replacement saves only draft');check(LPM_Service::resource($id)['description']===$original['description']&&LPM_Service::editor($id)['content']['style']===$current['style'],'CSV preserves metadata and individual styling');
$styles=LPM_Styling::get();$styles['header_bg']='#884422';LPM_Styling::save($styles);check(str_contains(LPM_Renderer::shortcode('price_table',['id'=>(string)$id]),'--lpm-header-bg:#884422'),'Global styling affects inherited published resource');
$import['style']['inherit']=false;$import['style']['values']['header_bg']='#225588';save2($id,$import);pub2($id);check(str_contains(LPM_Renderer::shortcode('price_table',['id'=>(string)$id]),'--lpm-header-bg:#225588'),'Individual styling overrides global');
$bad=$styles;$bad['header_bg']='red;position:fixed';rejects(fn()=>LPM_Styling::save($bad),'Arbitrary global CSS rejected');
$legacy=LPM_Legacy_Content::initial('price_table');$legacy['title']='Legacy';$legacy['items']=[['title'=>'Legacy package','price'=>'100','description'=>'Preserved','cta'=>['label'=>'Daftar','url'=>'https://example.com/daftar','new_tab'=>true]]];
$old=LPM_Service::create('price_table','Legacy upgrade');save2($old,$legacy);$lv=pub2($old);$raw=LPM_Service::version(LPM_Service::resource($old),$lv['version_id'])['snapshot_data'];
$migrated=LPM_Service::editor($old);check($migrated['legacy']&&$migrated['content']['rows'][0][3]['url']==='https://example.com/daftar','Legacy CTA maps losslessly into action cell');
check(str_contains(LPM_Renderer::shortcode('price_table',['id'=>(string)$old]),'Legacy package'),'Legacy production continues rendering without migration writes');
$m=$migrated['content'];rejects(function()use($old,$m){save2($old,$m);pub2($old);},'Migrated table requires choosing program');$m['program']='eksklusif';save2($old,$m);pub2($old);
check(LPM_Service::version(LPM_Service::resource($old),$lv['version_id'])['snapshot_data']===$raw,'Publishing upgraded draft preserves immutable v1 snapshot');
require_once ABSPATH.'wp-admin/includes/image.php';$upload=wp_upload_bits('lpm-v2.png',null,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7ioAAAAASUVORK5CYII='));$image=wp_insert_attachment(['post_title'=>'Product v2 image','post_mime_type'=>'image/png','post_status'=>'inherit'],$upload['file']);
$catalog=LPM_Content::initial('price_catalog');$catalog['items']=[['title'=>'','price'=>'','description'=>'','attachment_id'=>$image,'image_url'=>$upload['url'],'alt'=>'Artwork harga','caption'=>'Harga 2026','link'=>'https://example.com/image','new_tab'=>true,'cta'=>['label'=>'Hubungi','url'=>'https://example.com/cta','new_tab'=>false]]];$cid=LPM_Service::create('price_catalog','Gallery');save2($cid,$catalog);pub2($cid);$html=LPM_Renderer::shortcode('price_catalog',['id'=>(string)$cid]);
check(str_contains($html,'https://example.com/image')&&str_contains($html,'https://example.com/cta'),'Single image supports independent image hyperlink and CTA');
$catalog['mode']='carousel';rejects(fn()=>LPM_Content::validate('price_catalog',$catalog,true),'Carousel rejects fewer than two slides');$catalog['items'][]=array_replace($catalog['items'][0],['caption'=>'Slide kedua']);save2($cid,$catalog);pub2($cid);$html=LPM_Renderer::shortcode('price_catalog',['id'=>(string)$cid]);
check(str_contains($html,'data-lpm-carousel')&&str_contains($html,'Slide kedua')&&str_contains($html,'data-prev'),'Carousel output includes all slides and navigation for progressive enhancement');
$catalog['mode']='single';rejects(fn()=>LPM_Content::validate('price_catalog',$catalog,true),'Single rejects multiple images');
$catalog['mode']='carousel';$catalog['items'][0]['link']='javascript:alert(1)';rejects(fn()=>LPM_Content::validate('price_catalog',$catalog),'Executable image hyperlink rejected');
// REST list, filters and author access: no draft payload may leak through the library.
do_action('rest_api_init');function req2(string $method,string $path,array $params=[]){$r=new WP_REST_Request($method,'/lpm/v1'.$path);$r->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));foreach($params as $k=>$v){$r->set_param($k,$v);}return rest_do_request($r);}
$list=req2('GET','/resources',['type'=>'price_table','search'=>(string)$id]);check($list->get_status()===200&&in_array((string)$id,array_column($list->get_data()['items']??[],'id'),true),'Search and type filters work on actual MySQL metadata query');
check(req2('GET','/resources',['program'=>'eksklusif'])->get_status()===200,'Program filter query succeeds');check(req2('GET','/resources',['mode'=>'carousel'])->get_status()===200,'Catalog mode filter query succeeds');
$author=wp_create_user('p2-author-'.key2(),bin2hex(random_bytes(18)));(new WP_User($author))->set_role('author');wp_set_current_user($author);
$list=req2('GET','/resources')->get_data();check(isset($list['items'])&&!array_filter($list['items'],static fn($r)=>!$r['production_version_id']||isset($r['draft_data'])||isset($r['draft_program'])),'Author library contains only published resources and no draft fields');
check(req2('GET','/resources/'.$id)->get_status()===403,'Author cannot read editor draft');check(req2('GET','/resources/'.$id.'/published')->get_status()===200,'Author can preview published resource');check(req2('POST','/resources',['type'=>'price_table','name'=>'Unauthorized'])->get_status()===403,'Author cannot mutate pricing');
$private=wp_insert_post(['post_type'=>'post','post_title'=>'Secret admin draft','post_status'=>'draft','post_author'=>1,'post_content'=>'[latis_price_table id="'.$id.'"]']);
check(!in_array($private,array_column(LPM_Usage::posts($id,'price_table')['items'],'id'),true),'Usage detail hides another user private draft');wp_set_current_user(1);
echo "SUCCESS: $checks product-v2 checks passed.\n";
file_put_contents(__DIR__.'/runtime/demo-v2.json',json_encode(['table'=>$id,'catalog'=>$cid,'legacy'=>$old]));
