<?php
define('ABSPATH',__DIR__);define('LPM_FILE',dirname(__DIR__).'/price-catalogs/price-catalogs.php');define('LPM_VERSION','2.0.6');
function plugins_url($path,$file){return 'https://example.test/wp-content/plugins/price-catalogs/'.$path;}
$hooks=[];$icon='';$styles=[];
function add_action($hook,$callback,...$args){global $hooks;$hooks[$hook]=$callback;}
function add_filter(...$args){}
function add_menu_page($title,$label,$cap,$slug,$callback,$url,$position){global $icon;$icon=$url;}
function add_submenu_page(...$args){}
function wp_enqueue_style($handle,...$args){global $styles;$styles[]=$handle;}
function sanitize_key($value){return $value;}
require dirname(__DIR__).'/price-catalogs/includes/class-admin.php';
LPM_Admin::boot();$hooks['admin_menu']();
if($icon!=='https://example.test/wp-content/plugins/price-catalogs/assets/menu-icon.svg'){throw new RuntimeException('Menu is not using intrinsically sized icon.');}
echo "PASS menu uses intrinsically sized icon.\n";
$hooks['admin_enqueue_scripts']('index.php');
if($styles!==['lpm-menu']){throw new RuntimeException('Dashboard did not load only the scoped menu styles.');}
echo "PASS Dashboard loads menu sizing without the Pricelist application styles.\n";
