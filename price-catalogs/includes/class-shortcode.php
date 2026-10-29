<?php
defined('ABSPATH') || exit;

final class LPM_Shortcode {
    private static array $tags=[];
    private static bool $loaded=false;

    public static function slug(string $name): string {
        return sanitize_title($name)?:'paket';
    }

    public static function register(string $tag): bool {
        if(isset(self::$tags[$tag]))return true;
        // Never replace WordPress or another plugin's shortcode.
        if(shortcode_exists($tag))return false;
        self::$tags[$tag]=true;
        add_shortcode($tag,static function($atts){
            $atts=(array)$atts;$id=$atts['id']??'';
            if(!ctype_digit((string)$id)||(int)$id<1)return '';
            try{$resource=LPM_Service::resource((int)$id);return LPM_Renderer::shortcode($resource['resource_type'],$atts);}
            catch(Throwable $e){return '';}
        });
        return true;
    }

    public static function boot(): void {
        if(self::$loaded)return;
        global $wpdb;
        try{
            // Keep archived names registered so deleted embeds render empty.
            $rows=LPM_Database::rows('SELECT name FROM '.LPM_Schema::table('resources'));
            $prefix=$wpdb->esc_like('lpm_shortcode_tag_').'%';
            $aliases=$wpdb->get_col($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name LIKE %s",$prefix));
            foreach($rows as $row)self::register(self::slug($row['name']));
            foreach($aliases as $tag)if(is_string($tag)&&self::slug($tag)===$tag)self::register($tag);
            self::$loaded=true;
        }catch(Throwable $e){/* Legacy fixed tags remain available if the database is unavailable. */}
    }

    public static function tags(): array {
        self::boot();return array_merge(['latis_price_table','latis_price_catalog'],array_keys(self::$tags));
    }

    public static function remember(array $resource): void {
        $tag=self::slug($resource['name']);
        $key='lpm_shortcode_tag_'.hash('sha256',$tag);
        // One immutable option per alias avoids lost updates between editors.
        if(!add_option($key,$tag,'',false)&&get_option($key)!==$tag){
            throw new RuntimeException('Alias shortcode lama belum tersimpan. Ulangi penyimpanan nama paket.');
        }
    }

    public static function make(array $resource): string {
        $name=self::slug($resource['name']);
        if(self::register($name))return '['.$name.' id="'.(int)$resource['id'].'"]';
        $tag=$resource['resource_type']==='price_table'?'latis_price_table':'latis_price_catalog';
        return '['.$tag.' nama="'.$name.'" id="'.(int)$resource['id'].'"]';
    }
}
