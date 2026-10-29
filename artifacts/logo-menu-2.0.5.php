<?php
// Isolated hook fixture; no database required for a logo-only release.
define('ABSPATH',__DIR__);define('LPM_FILE',dirname(__DIR__).'/price-catalogs/price-catalogs.php');
function plugins_url($path,$file){return 'https://example.test/wp-content/plugins/price-catalogs/'.$path;}
$hooks=[];$icon='';
function add_action($hook,$callback,...$args){global $hooks;$hooks[$hook]=$callback;}
function add_filter(...$args){}
function add_menu_page($title,$label,$cap,$slug,$callback,$url,$position){global $icon;$icon=$url;}
function add_submenu_page(...$args){}
require dirname(__DIR__).'/price-catalogs/includes/class-admin.php';
LPM_Admin::boot();$hooks['admin_menu']();
if($icon!=='https://example.test/wp-content/plugins/price-catalogs/assets/logo.png'){throw new RuntimeException('Logo URL is not bound to menu.');}
echo "PASS WordPress menu hook uses the bundled original logo.\n";
