<?php
defined('ABSPATH') || exit;

/** Read-only shortcuts for writers. Uses the same published-only library API. */
final class LPM_Writer {
    public const DOCUMENTATION = 'https://www.mtalkblog.com/p/plugin-price-catalogs.html';

    public static function boot(): void {
        add_action('admin_bar_menu', [self::class, 'admin_bar'], 90);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('media_buttons', [self::class, 'classic_button'], 20);
        add_action('enqueue_block_editor_assets', [self::class, 'block_editor']);
        add_action('add_meta_boxes', [self::class, 'meta_boxes']);
        add_filter('plugin_action_links_' . plugin_basename(LPM_FILE), [self::class, 'action_links']);
    }

    public static function admin_bar(WP_Admin_Bar $bar): void {
        if (!current_user_can('edit_posts')&&!current_user_can('edit_pages')) { return; }
        $bar->add_node(['id'=>'lpm-pricelist','title'=>'Pricelist','href'=>admin_url('admin.php?page=lpm')]);
        $bar->add_node(['id'=>'lpm-documentation','parent'=>'lpm-pricelist','title'=>'Dokumentasi','href'=>self::DOCUMENTATION,
            'meta'=>['target'=>'_blank','rel'=>'noopener noreferrer']]);
    }

    public static function action_links(array $links): array {
        if (current_user_can('edit_posts')||current_user_can('edit_pages')) {
            array_unshift($links, '<a href="'.esc_url(admin_url('admin.php?page=lpm')).'">Pricelist</a>');
        }
        $links[]='<a href="'.esc_url(self::DOCUMENTATION).'" target="_blank" rel="noopener noreferrer">Dokumentasi</a>';
        return $links;
    }

    private static function editor_screen(): bool {
        $screen=get_current_screen();
        return $screen && $screen->base==='post' && post_type_supports($screen->post_type,'editor') && self::can_edit_type($screen->post_type);
    }

    private static function can_edit_type(string $type): bool {
        $object=get_post_type_object($type);
        return $object && current_user_can($object->cap->edit_posts);
    }

    public static function meta_boxes(): void {
        foreach(['post','page'] as $type){
            if(!self::can_edit_type($type))continue;
            add_meta_box('lpm-shortcodes','Pricelist — Shortcode',[self::class,'widget'],$type,'side','default',
                ['__block_editor_compatible_meta_box'=>true]);
        }
    }

    public static function widget(): void {
        echo '<div class="lpm-writer-widget"><p>Cari nama paket atau ID untuk menyisipkan tabel harga dan katalog yang sudah terbit.</p><button type="button" class="button button-primary lpm-writer-open" data-editor="content">Pilih &amp; sisipkan shortcode</button><p><a href="'.esc_url(admin_url('admin.php?page=lpm')).'" target="_blank" rel="noopener noreferrer">Buka pustaka Pricelist</a></p><small>Latis Price Catalogs v'.esc_html(LPM_VERSION).' · Galih Enggarnanto</small></div>';
    }

    public static function enqueue(): void {
        if (!self::editor_screen()) { return; }
        wp_enqueue_style('lpm-writer',plugins_url('assets/writer.css',LPM_FILE),[],LPM_VERSION);
        wp_enqueue_script('lpm-writer',plugins_url('assets/writer.js',LPM_FILE),[],LPM_VERSION,true);
        wp_localize_script('lpm-writer','LPMWriter',['root'=>esc_url_raw(rest_url('lpm/v1')),'nonce'=>wp_create_nonce('wp_rest'),
            'library'=>admin_url('admin.php?page=lpm'),'documentation'=>self::DOCUMENTATION,'version'=>LPM_VERSION,'author'=>'Galih Enggarnanto']);
    }

    public static function classic_button(string $editor_id='content'): void {
        if (!self::editor_screen()) { return; }
        echo '<button type="button" class="button lpm-writer-open" data-editor="'.esc_attr($editor_id).'"><span class="dashicons dashicons-tag" aria-hidden="true"></span> Sisipkan Pricelist</button>';
    }

    public static function block_editor(): void {
        if (!self::editor_screen()) { return; }
        wp_enqueue_script('lpm-writer-block',plugins_url('assets/writer-block.js',LPM_FILE),
            ['lpm-writer','wp-plugins','wp-editor','wp-edit-post','wp-element','wp-components','wp-blocks','wp-data'],LPM_VERSION,true);
    }
}
