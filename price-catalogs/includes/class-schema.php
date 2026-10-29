<?php
defined('ABSPATH') || exit;

final class LPM_Schema {
    public const VERSION = '4';

    public static function table(string $name): string {
        global $wpdb;
        if (!in_array($name, ['resources', 'resource_versions', 'cache_events', 'usage', 'usage_refs'], true)) {
            throw new InvalidArgumentException('Unknown table.');
        }
        return $wpdb->prefix . 'lpm_' . $name;
    }

    public static function activate(): void {
        try { self::install(); }
        catch (Throwable $e) { wp_die(esc_html($e->getMessage())); }
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $previous_errors=$wpdb->suppress_errors(true);
        $json_supported=$wpdb->get_var("SELECT JSON_UNQUOTE(JSON_EXTRACT('{\"ready\":\"yes\"}', '$.ready'))");
        $wpdb->suppress_errors($previous_errors);
        if($json_supported!=='yes'){throw new RuntimeException('Pricelist v2 membutuhkan MySQL 5.7+ atau MariaDB 10.3+ dengan fungsi JSON dan InnoDB.');}
        $collate = $wpdb->get_charset_collate();
        $r = self::table('resources');
        $v = self::table('resource_versions');
        $c = self::table('cache_events');
        dbDelta("CREATE TABLE $r (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            resource_type varchar(20) NOT NULL,
            name varchar(200) NOT NULL,
            description text DEFAULT NULL,
            updated_at datetime DEFAULT NULL,
            updated_by bigint unsigned DEFAULT NULL,
            deleted_at datetime DEFAULT NULL,
            production_version_id bigint unsigned DEFAULT NULL,
            draft_data longtext DEFAULT NULL,
            draft_revision bigint unsigned NOT NULL DEFAULT 0,
            draft_base_version_id bigint unsigned DEFAULT NULL,
            draft_updated_at datetime DEFAULT NULL,
            draft_updated_by bigint unsigned DEFAULT NULL,
            last_version_number bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB $collate;");
        $usage = self::table('usage'); $refs = self::table('usage_refs');
        dbDelta("CREATE TABLE $usage (
            post_id bigint unsigned NOT NULL,
            resource_type varchar(20) NOT NULL,
            resource_id bigint unsigned NOT NULL,
            PRIMARY KEY  (post_id,resource_type,resource_id),
            KEY resource (resource_type,resource_id)
        ) ENGINE=InnoDB $collate;");
        dbDelta("CREATE TABLE $refs (
            post_id bigint unsigned NOT NULL,
            ref_id bigint unsigned NOT NULL,
            issue varchar(100) DEFAULT NULL,
            PRIMARY KEY  (post_id,ref_id),
            KEY reference_id (ref_id)
        ) ENGINE=InnoDB $collate;");
        dbDelta("CREATE TABLE $v (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            resource_type varchar(20) NOT NULL,
            resource_id bigint unsigned NOT NULL,
            version_number bigint unsigned NOT NULL,
            snapshot_data longtext NOT NULL,
            snapshot_schema_version int unsigned NOT NULL,
            change_summary text NOT NULL,
            published_by bigint unsigned NOT NULL,
            published_at datetime NOT NULL,
            publication_type varchar(20) NOT NULL,
            restored_from_version_id bigint unsigned DEFAULT NULL,
            operation_key varchar(64) NOT NULL,
            operation_payload_hash char(64) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY resource_version (resource_type,resource_id,version_number),
            UNIQUE KEY operation (resource_type,resource_id,operation_key),
            KEY history (resource_id,id)
        ) ENGINE=InnoDB $collate;");
        dbDelta("CREATE TABLE $c (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            resource_id bigint unsigned NOT NULL,
            resource_type varchar(20) NOT NULL,
            version_id bigint unsigned NOT NULL,
            event_type varchar(20) NOT NULL DEFAULT 'publish',
            created_at datetime NOT NULL,
            attempts int unsigned NOT NULL DEFAULT 0,
            retry_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            last_error varchar(200) DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_transition (version_id,event_type),
            KEY pending (completed_at,retry_at)
        ) ENGINE=InnoDB $collate;");
        // dbDelta adds the transition key but does not remove the old one-column key.
        if($wpdb->get_var("SHOW INDEX FROM $c WHERE Key_name = 'version_event'")){
            if($wpdb->query("ALTER TABLE $c DROP INDEX version_event")===false)throw new RuntimeException('Indeks cache belum dapat diperbarui.');
        }
        self::ready();
        update_option('lpm_db_version', self::VERSION, false);
    }

    public static function ready(): void {
        global $wpdb;
        foreach (['resources', 'resource_versions', 'cache_events', 'usage', 'usage_refs'] as $name) {
            $engine = $wpdb->get_var($wpdb->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                self::table($name)
            ));
            if (strtoupper((string) $engine) !== 'INNODB') {
                throw new RuntimeException('Versioning membutuhkan seluruh tabel LPM dengan engine InnoDB. Periksa database sebelum melanjutkan.');
            }
        }
        $cache=self::table('cache_events');
        if(!$wpdb->get_var("SHOW COLUMNS FROM $cache LIKE 'event_type'")||!$wpdb->get_var("SHOW INDEX FROM $cache WHERE Key_name = 'event_transition'")||$wpdb->get_var("SHOW INDEX FROM $cache WHERE Key_name = 'version_event'")){
            throw new RuntimeException('Schema antrean cache belum siap. Jalankan ulang pembaruan plugin sebelum mengubah harga.');
        }
    }
}
