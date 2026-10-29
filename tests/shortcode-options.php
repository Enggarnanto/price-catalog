<?php
// Isolated logic tests with WordPress API doubles; no database or production access.
define('ABSPATH',__DIR__.'/');define('LPM_VERSION','2.0.10');
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function esc_attr($v){return esc_html($v);}function esc_url($v){return esc_html($v);}
function wp_check_invalid_utf8($v){return mb_check_encoding($v,'UTF-8')?$v:'';}
function wp_parse_url($v,$part=-1){return parse_url($v,$part);}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function get_option($key,$default=[]){global $options;return $options[$key]??$default;}
function sanitize_title($v){return trim(preg_replace('/[^a-z0-9]+/','-',strtolower($v)),'-');}
function shortcode_exists($tag){global $shortcode_tags;return isset($shortcode_tags[$tag]);}
function add_shortcode($tag,$callback){global $shortcode_tags;$shortcode_tags[$tag]=$callback;}
function admin_url($v){return 'https://example.test/wp-admin/'.$v;}
$caps=['edit_posts'=>true,'edit_pages'=>true];$boxes=[];
function current_user_can($v){global $caps;return $caps[$v]??false;}
function get_post_type_object($v){return (object)['cap'=>(object)['edit_posts'=>$v==='page'?'edit_pages':'edit_posts']];}
function add_meta_box(...$args){global $boxes;$boxes[]=$args;}
class WP_REST_Request{
 public function __construct(private string $method,private string $route,private string $nonce='valid'){}
 public function get_method(){return $this->method;}public function get_route(){return $this->route;}public function get_header($key){return $this->nonce;}
}
class WP_Error{public function __construct(...$args){}}
function wp_verify_nonce($value,$action){return $value==='valid';}
class LPM_Service{
 public static array $content=[];
 public static function resource($id){if($id===3)throw new InvalidArgumentException('Archived');return ['id'=>$id,'resource_type'=>$id===2?'price_catalog':'price_table','production_version_id'=>1];}
 public static function version($r,$id){return [];}
 public static function snapshot($v){return self::$content;}
}
foreach(['legacy-content','styling','content-v2','renderer-v2','shortcode','writer','admin'] as $part)require __DIR__.'/../price-catalogs/includes/class-'.$part.'.php';
$checks=0;function check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);echo 'PASS '.(++$checks).' '.$label."\n";}
$d=LPM_Content::initial('price_table');$d['title']='Judul Paket';$d['description']='Deskripsi Paket';
check(str_contains(LPM_Renderer::render('price_table',$d),'Deskripsi Paket'),'Existing schema 2 descriptions remain visible');
$d['presentation']=LPM_Content::presentation($d['headers']);
check(LPM_Content::schema($d)===3,'Existing presentation snapshot remains schema 3');
$d['show_description']=false;
check(LPM_Content::schema($d)===4&&LPM_Content::validate('price_table',$d)===$d,'Description visibility declares and validates schema 4');
$html=LPM_Renderer::render('price_table',$d);
check(str_contains($html,'Judul Paket')&&!str_contains($html,'Deskripsi Paket'),'Description can be hidden independently');
$d['show_title']=false;$d['show_description']=true;$html=LPM_Renderer::render('price_table',$d);
check(!str_contains($html,'Judul Paket')&&str_contains($html,'Deskripsi Paket'),'Title can be hidden independently');
$bad=$d;$bad['show_description']='false';try{LPM_Content::validate('price_table',$bad);throw new RuntimeException('Accepted invalid toggle');}catch(InvalidArgumentException $e){check(true,'Visibility must be a boolean');}
LPM_Service::$content=$d;$original=$d;
check(str_contains(LPM_Renderer::shortcode('price_table',['id'=>'1','nama'=>'nama-lama','show_title'=>'1','show_description'=>'0']),'Judul Paket'),'Named shortcode resolves by stable ID and overrides title');
check(!str_contains(LPM_Renderer::shortcode('price_table',['id'=>'1','show_description'=>'0']),'Deskripsi Paket'),'Shortcode hides description per article');
check(LPM_Service::$content===$original,'Overrides do not mutate stored snapshots');
check(str_contains(LPM_Renderer::shortcode('price_table',['id'=>'1']),'Deskripsi Paket'),'Old ID-only shortcode still renders');
check(LPM_Renderer::shortcode('price_catalog',['id'=>'1'])==='','Resource type mismatch remains blocked');
$c=LPM_Content::initial('price_catalog');$c['show_description']=false;$c['description']='Deskripsi Katalog';
check(!str_contains(LPM_Renderer::render('price_catalog',$c),'Deskripsi Katalog'),'Catalog supports independent description visibility');
check(LPM_Shortcode::make(['id'=>12,'name'=>'Paket Reguler','resource_type'=>'price_table'])==='[paket-reguler id="12"]','Shortcode tag is package name with stable ID');
LPM_Writer::meta_boxes();check(array_column($boxes,3)===['post','page'],'Shortcode widgets registered for Posts and Pages');
$caps=['edit_pages'=>true];$boxes=[];LPM_Writer::meta_boxes();check(array_column($boxes,3)===['page'],'Page-only editor receives Page widget');
ob_start();LPM_Writer::widget();$html=ob_get_clean();check(str_contains($html,'v2.0.10')&&str_contains($html,'Galih Enggarnanto'),'Widget displays version and creator');
check(LPM_Admin::permission(new WP_REST_Request('GET','/lpm/v1/resources'))===true,'Page-only editors can search shortcode library');
check(LPM_Admin::permission(new WP_REST_Request('POST','/lpm/v1/resources/1/save')) instanceof WP_Error,'Page widget does not grant price mutation access');
check(LPM_Admin::permission(new WP_REST_Request('GET','/lpm/v1/resources','invalid')) instanceof WP_Error,'Shortcode library still requires a valid nonce');
echo 'SUCCESS '.$checks." shortcode options checks\n";
