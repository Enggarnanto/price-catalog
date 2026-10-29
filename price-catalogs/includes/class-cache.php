<?php
defined('ABSPATH') || exit;

final class LPM_Cache {
    public static function boot(): void {
        add_action('lpm_cache_retry', [self::class, 'process']);
        if (!wp_next_scheduled('lpm_cache_retry')) { wp_schedule_event(time() + 60, 'hourly', 'lpm_cache_retry'); }
    }

    public static function pending(int $id): int {
        global $wpdb;
        return (int) LPM_Database::scalar($wpdb->prepare('SELECT COUNT(*) FROM ' . LPM_Schema::table('cache_events') . ' WHERE resource_id = %d AND completed_at IS NULL', $id));
    }

    public static function process(bool $force = false): void {
        global $wpdb;
        LPM_Schema::ready();
        $table = LPM_Schema::table('cache_events');
        // A database advisory lock prevents overlapping workers; no publication row locks are held here.
        $lock = 'lpm_cache_' . substr(hash('sha256', DB_NAME . $wpdb->prefix), 0, 32);
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== '1') { return; }
        try {
            $where = $force ? '' : $wpdb->prepare(' AND retry_at <= %s', gmdate('Y-m-d H:i:s'));
            $events = LPM_Database::rows("SELECT * FROM $table WHERE completed_at IS NULL $where ORDER BY id ASC LIMIT 20");
            foreach ($events as $event) {
                $attempt = (int) $event['attempts'] + 1;
                try {
                    // Integrations must be idempotent and throw on failure so this event is retried.
                    do_action('lpm_invalidate_resource_cache', (int) $event['resource_id'], $event['resource_type'], (int) $event['version_id'], (int) $event['id']);
                    $wpdb->update($table, ['completed_at' => gmdate('Y-m-d H:i:s'), 'attempts' => $attempt, 'last_error' => null], ['id' => $event['id']]);
                } catch (Throwable $e) {
                    $wpdb->update($table, ['attempts' => $attempt, 'retry_at' => gmdate('Y-m-d H:i:s', time() + min(3600, 30 * (2 ** min(7, $attempt)))),
                        'last_error' => 'Invalidasi gagal; akan dicoba ulang.'], ['id' => $event['id']]);
                    error_log('LPM cache event failed: event=' . (int) $event['id']);
                }
            }
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
    }
}
