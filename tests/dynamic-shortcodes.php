<?php
// Lifecycle and rendering tests with isolated WordPress/database API doubles.
require __DIR__.'/shortcode-options.php';
$options=[];
function add_option($key,$value,$deprecated='',$autoload=false){global $options;if(isset($options[$key]))return false;$options[$key]=$value;return true;}
class LPM_Schema{public static function table($name){return $name;}}
class LPM_Database{public static function rows($sql){return [['name'=>'Paket Baru'],['name'=>'Gallery']];}}
class DynamicTestWpdb{
 public $options='wp_options';
 public function esc_like($v){return $v;}public function prepare($query,...$args){return $query;}
 public function get_col($sql){global $options;return array_values($options);}
}
$wpdb=new DynamicTestWpdb;
LPM_Shortcode::remember(['name'=>'Paket Lama']);
check(in_array('paket-lama',$options,true),'Rename preserves old name as a persistent alias');
LPM_Shortcode::remember(['name'=>'Paket Lama']);check(count($options)===1,'Remembering an existing alias is idempotent');
$other=static fn()=>'WordPress gallery';add_shortcode('gallery',$other);
LPM_Shortcode::boot();
check(shortcode_exists('paket-baru')&&shortcode_exists('paket-lama'),'Startup registers current names and retained aliases');
check($shortcode_tags['gallery']===$other,'Existing third-party shortcode is never replaced');
check(LPM_Shortcode::make(['id'=>1,'name'=>'Gallery','resource_type'=>'price_table'])==='[latis_price_table nama="gallery" id="1"]','Reserved tag uses compatible fixed shortcode fallback');
check(in_array('paket-lama',LPM_Shortcode::tags(),true)&&in_array('latis_price_catalog',LPM_Shortcode::tags(),true),'Usage scan receives dynamic names, aliases and legacy tags');
$d=LPM_Content::initial('price_table');$d['title']='Harga Paket';$d['description']='Deskripsi Paket';LPM_Service::$content=$d;
check(str_contains($shortcode_tags['paket-baru'](['id'=>'1']),'Harga Paket'),'Dynamic table shortcode renders published resource');
check(str_contains($shortcode_tags['paket-lama'](['id'=>'1']),'Harga Paket'),'Retired name keeps rendering by ID');
check(!str_contains($shortcode_tags['paket-baru'](['id'=>'1','show_title'=>'0']),'Harga Paket'),'Dynamic tag honors per-article visibility');
check($shortcode_tags['paket-baru']([])===''&&$shortcode_tags['paket-baru'](['id'=>'bad'])==='','Dynamic tag requires a valid resource ID');
check($shortcode_tags['paket-baru'](['id'=>'3'])==='','Archived resource renders empty through a retained name');
LPM_Service::$content=LPM_Content::initial('price_catalog');LPM_Service::$content['title']='Katalog Paket';
check(str_contains($shortcode_tags['paket-baru'](['id'=>'2']),'Katalog Paket'),'Dynamic tag resolves catalog type from resource ID');
check(LPM_Shortcode::make(['id'=>8,'name'=>'!!!','resource_type'=>'price_table'])==='[paket id="8"]','Empty sanitized name has a usable fallback');
echo "SUCCESS dynamic shortcode lifecycle checks\n";
