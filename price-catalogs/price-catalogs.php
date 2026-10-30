<?php
/**
 * Plugin Name: Latis Price Catalogs
 * Plugin URI: https://www.mtalkblog.com/p/plugin-price-catalogs.html
 * Description: Versioned price tables and image catalogs with safe publishing and restore.
 * Version: 2.4.2
 * Update URI: https://www.mtalkblog.com/p/plugin-price-catalogs.html
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Author: Galih Enggarnanto
 * Author URI: https://www.mtalkblog.com/
 * License: GPL-2.0-or-later
 * Text Domain: latis-price-catalogs
 */
defined('ABSPATH') || exit;
define('LPM_FILE', __FILE__);
define('LPM_VERSION', '2.4.2');
foreach (['schema', 'database', 'legacy-content', 'styling', 'money', 'content-v2', 'service', 'legacy-renderer', 'renderer-v2', 'cache', 'usage', 'csv', 'json-import', 'shortcode', 'admin', 'writer', 'updater'] as $lpm_part) {
    require_once __DIR__ . '/includes/class-' . $lpm_part . '.php';
}
register_activation_hook(__FILE__, ['LPM_Schema', 'activate']);
register_deactivation_hook(__FILE__, static function () { wp_clear_scheduled_hook('lpm_cache_retry'); wp_clear_scheduled_hook('lpm_usage_rebuild'); });
add_action('plugins_loaded', static function () {
    if (get_option('lpm_db_version') !== LPM_Schema::VERSION) {
        try { LPM_Schema::install(); } catch (Throwable $e) { /* Mutations remain blocked; admin displays readiness. */ }
    }
    LPM_Cache::boot();
    LPM_Admin::boot();
    LPM_Writer::boot();
    LPM_Updater::boot();
    LPM_Usage::boot();
    add_action('init',[LPM_Shortcode::class,'boot'],100);
    add_shortcode('latis_price_table', static fn($atts) => LPM_Renderer::shortcode('price_table', (array) $atts));
    add_shortcode('latis_price_catalog', static fn($atts) => LPM_Renderer::shortcode('price_catalog', (array) $atts));
    add_action('wp_enqueue_scripts', static function () {
        wp_enqueue_style('lpm-public', plugins_url('assets/public.css', LPM_FILE), [], LPM_VERSION);
    });
});
