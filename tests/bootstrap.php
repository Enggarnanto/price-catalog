<?php
// Isolated test environment. Never reads an existing WordPress wp-config.php.
if (PHP_SAPI !== 'cli' && getenv('LPM_ALLOW_TEST_WEB') !== '1') { http_response_code(403); exit; }
$core = getenv('LPM_WP_CORE');
if (!$core || !is_file($core . '/wp-settings.php')) { throw new RuntimeException('Set LPM_WP_CORE to a WordPress core directory.'); }
define('ABSPATH', rtrim(str_replace('\\', '/', $core), '/') . '/');
define('WP_INSTALLING', true);
define('WP_USE_THEMES', false);
define('DB_NAME', 'lpm_integration');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', '127.0.0.1:13317');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
define('WP_CONTENT_DIR', __DIR__ . '/runtime/content');
define('WP_CONTENT_URL', 'http://127.0.0.1:18317/content');
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
define('WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins');
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('DISABLE_WP_CRON', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('AUTH_KEY', 'isolated-lpm-test-auth-not-for-production');
define('SECURE_AUTH_KEY', 'isolated-lpm-test-secure-not-for-production');
define('LOGGED_IN_KEY', 'isolated-lpm-test-logged-not-for-production');
define('NONCE_KEY', 'isolated-lpm-test-nonce-not-for-production');
define('AUTH_SALT', 'isolated-lpm-test-auth-salt');
define('SECURE_AUTH_SALT', 'isolated-lpm-test-secure-salt');
define('LOGGED_IN_SALT', 'isolated-lpm-test-logged-salt');
define('NONCE_SALT', 'isolated-lpm-test-nonce-salt');
define('LPM_TESTING', true);
$table_prefix = 'lpmtest_';
foreach (['themes', 'plugins'] as $folder) {
    if (!is_dir(WP_CONTENT_DIR . '/' . $folder)) { mkdir(WP_CONTENT_DIR . '/' . $folder, 0777, true); }
}
$_SERVER['HTTP_HOST'] = '127.0.0.1:18317';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
require ABSPATH . 'wp-settings.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if (!is_blog_installed()) {
    wp_install('LPM isolated tests', 'lpm-test-admin', 'test@example.invalid', false, '', bin2hex(random_bytes(20)));
}
update_option('siteurl', 'http://127.0.0.1:18317');
update_option('home', 'http://127.0.0.1:18317');
require_once dirname(__DIR__) . '/price-catalogs/price-catalogs.php';
LPM_Admin::boot();
LPM_Cache::boot();
add_shortcode('latis_price_table', static fn($atts) => LPM_Renderer::shortcode('price_table', (array) $atts));
add_shortcode('latis_price_catalog', static fn($atts) => LPM_Renderer::shortcode('price_catalog', (array) $atts));
wp_set_current_user(1);
