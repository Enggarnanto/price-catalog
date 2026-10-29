<?php
defined('ABSPATH') || exit;

final class LPM_Conflict extends RuntimeException {}

final class LPM_Service {
    private static ?mysqli $transaction_connection = null;
    /** Only used by the isolated integration test harness. */
    public static $test_fault = null;

    public static function authorize(): void {
        if (!current_user_can('manage_options')) { throw new RuntimeException('Akses ditolak.', 403); }
    }

    private static function query(string $sql): int {
        $result = self::execute($sql);
        if ($result instanceof mysqli_result) { $result->free(); }
        return mysqli_affected_rows(self::connection());
    }

    private static function connection(): mysqli {
        $connection = self::$transaction_connection ?? LPM_Database::connection();
        if (!$connection instanceof mysqli) { throw new RuntimeException('Koneksi mysqli diperlukan.'); }
        return $connection;
    }

    private static function execute(string $sql) {
        // wpdb::query may reconnect and replay a statement after a lost connection.
        // That could replay a transactional write in autocommit mode. Pin the native
        // connection and never reconnect/replay inside a transaction instead.
        return LPM_Database::execute($sql,self::connection());
    }

    private static function row(string $sql): ?array {
        $result = self::execute($sql);
        if (!$result instanceof mysqli_result) { throw new RuntimeException('Pembacaan database gagal.'); }
        $row = $result->fetch_assoc(); $result->free(); return $row;
    }

    private static function fields(array $data): array {
        global $wpdb;
        $columns = []; $values = [];
        foreach ($data as $column => $value) {
            if (!preg_match('/^[a-z_]+$/D', $column)) { throw new InvalidArgumentException('Invalid internal field.'); }
            $columns[] = '`' . $column . '`';
            $values[] = $value === null ? 'NULL' : $wpdb->prepare('%s', (string) $value);
        }
        return [$columns, $values];
    }

    private static function insert(string $table, array $data): int {
        [$columns, $values] = self::fields($data);
        self::query('INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')');
        return (int) mysqli_insert_id(self::connection());
    }

    private static function update(array $data, int $id): void {
        [$columns, $values] = self::fields($data);
        $sets = [];
        foreach ($columns as $index => $column) { $sets[] = $column . '=' . $values[$index]; }
        self::query('UPDATE `' . LPM_Schema::table('resources') . '` SET ' . implode(',', $sets) . ' WHERE id=' . (int) $id);
    }

    private static function transaction(int $id, callable $action,?callable $verify=null,bool $allow_deleted=false) {
        global $wpdb;
        self::authorize(); LPM_Schema::ready();
        if (self::$transaction_connection !== null) { throw new RuntimeException('Nested versioning transaction rejected.'); }
        self::$transaction_connection = self::connection();
        try {
            if(!mysqli_begin_transaction(self::connection())){throw new LPM_Persistence_Error('Transaksi penyimpanan gagal dimulai.');}
            $row = self::row($wpdb->prepare('SELECT * FROM ' . LPM_Schema::table('resources') . ' WHERE id = %d FOR UPDATE', $id));
            if (!$row) { throw new InvalidArgumentException('Resource tidak ditemukan.'); }
            if (!$allow_deleted && !empty($row['deleted_at'])) { throw new LPM_Conflict('Tabel atau katalog ini sudah dihapus. Isian lokal tetap aman.'); }
            $result = $action($row);
            self::fault('before_transaction_commit');
            if(!mysqli_commit(self::connection())){throw new LPM_Persistence_Error('Commit penyimpanan belum terkonfirmasi.');}
            self::fault('after_transaction_commit');
            if($verify){
                $observer=LPM_Database::open();
                try{$result=$verify($result,$observer);}finally{mysqli_close($observer);}
            }
            return $result;
        } catch (Throwable $e) {
            try { mysqli_rollback(self::connection()); } catch (Throwable $ignored) { /* Commit may already have completed; retries reconcile by operation key. */ }
            error_log('LPM operation failed: resource=' . $id . ' category=' . get_class($e));
            throw $e;
        } finally { self::$transaction_connection = null; }
    }

    public static function create(string $type, string $name): int {
        self::authorize(); LPM_Schema::ready();
        if (!in_array($type, ['price_table', 'price_catalog'], true) || trim($name) === '' || mb_strlen($name) > 200) {
            throw new InvalidArgumentException('Tipe atau nama resource tidak valid.');
        }
        return self::insert(LPM_Schema::table('resources'), ['resource_type' => $type, 'name' => sanitize_text_field($name), 'updated_at'=>gmdate('Y-m-d H:i:s'),'updated_by'=>get_current_user_id()]);
    }

    public static function resource(int $id): array {
        global $wpdb;
        $row = LPM_Database::row($wpdb->prepare('SELECT * FROM ' . LPM_Schema::table('resources') . ' WHERE id = %d', $id));
        if (!$row || !empty($row['deleted_at'])) { throw new InvalidArgumentException('Resource tidak ditemukan atau sudah dihapus.'); }
        return $row;
    }

    public static function version(array $resource, int $version_id): array {
        global $wpdb;
        $sql = $wpdb->prepare('SELECT * FROM ' . LPM_Schema::table('resource_versions') . ' WHERE id = %d AND resource_id = %d AND resource_type = %s', $version_id, $resource['id'], $resource['resource_type']);
        $row = self::$transaction_connection ? self::row($sql) : LPM_Database::row($sql);
        if (!$row) { throw new InvalidArgumentException('Versi tidak ditemukan pada resource ini.'); }
        return $row;
    }

    public static function snapshot(array $version): array {
        return LPM_Content::validate($version['resource_type'], LPM_Content::decode($version['snapshot_data']), false, (int) $version['snapshot_schema_version']);
    }

    private static function check_revision(array $r, int $revision): void {
        if ((int) $r['draft_revision'] !== $revision) { throw new LPM_Conflict('Ada perubahan dari sesi lain. Isian Anda tetap aman; coba Simpan Draft lagi.'); }
    }

    public static function save(int $id, array $data, int $revision, int $base, bool $reconcile = false, ?int $expected_production = null, ?array $metadata = null): array {
        return self::transaction($id, static function ($r) use ($data, $revision, $base, $reconcile, $expected_production, $metadata) {
            self::check_revision($r, $revision);
            $production = (int) $r['production_version_id'];
            $original_base = $r['draft_data'] !== null ? (int) $r['draft_base_version_id'] : $production;
            if ($reconcile) {
                if ($expected_production === null || $expected_production !== $production || $base !== $production) {
                    throw new LPM_Conflict('Production berubah selama rekonsiliasi. Tinjau ulang perbandingan.');
                }
            } elseif ($base !== $original_base) { throw new LPM_Conflict('Base draft berubah. Muat ulang data sebelum menyimpan.'); }
            $data = LPM_Content::validate($r['resource_type'], $data);
            $update = ['draft_data' => LPM_Content::json($data), 'draft_revision' => $revision + 1,
                'draft_base_version_id' => $base ?: null, 'draft_updated_at' => gmdate('Y-m-d H:i:s'), 'draft_updated_by' => get_current_user_id(),
                'updated_at'=>gmdate('Y-m-d H:i:s'),'updated_by'=>get_current_user_id()];
            if ($metadata !== null) {
                $name = trim(LPM_Content::text($metadata['name']??null,200));
                if ($name==='') { throw new InvalidArgumentException('Nama internal wajib diisi.'); }
                if($name!==$r['name']){LPM_Shortcode::remember($r);}
                $update['name']=$name; $update['description']=LPM_Content::text($metadata['description']??'',1000);
            }
            self::update($update, (int) $r['id']);
            $persisted = self::row('SELECT draft_data,draft_revision FROM '.LPM_Schema::table('resources').' WHERE id='.(int)$r['id']);
            if (!$persisted || (int)$persisted['draft_revision'] !== $revision+1 || $persisted['draft_data'] !== $update['draft_data']) { throw new RuntimeException('Draft belum tersimpan dengan benar. Input tetap dipertahankan.'); }
            return ['revision' => $revision + 1, 'base' => $base, 'production' => $production, 'content'=>LPM_Content::decode($persisted['draft_data'])];
        },static function($result,mysqli $observer)use($id,$metadata){
            global $wpdb;
            $r=LPM_Database::row($wpdb->prepare('SELECT * FROM '.LPM_Schema::table('resources').' WHERE id=%d',$id),$observer);
            $expected=LPM_Content::json($result['content']);
            if(!$r||(int)$r['draft_revision']!==$result['revision']||$r['draft_data']!==$expected||($metadata!==null&&($r['name']!==trim($metadata['name'])||$r['description']!==($metadata['description']??'')))){
                throw new LPM_Persistence_Error('Draft belum terverifikasi setelah commit. Jangan tutup editor; input tetap dipertahankan.');
            }
            $result['persisted']=true;$result['content_hash']=hash('sha256',$expected);$result['shortcode']=LPM_Shortcode::make($r);return $result;
        });
    }

    public static function publish(int $id, int $revision, int $expected_production, string $summary, string $key, ?int $restore_id = null): array {
        self::authorize();
        if (!preg_match('/^[a-zA-Z0-9_-]{20,64}$/D', $key)) { throw new InvalidArgumentException('Operation key tidak valid.'); }
        $summary = trim($summary);
        if ($summary === '' || mb_strlen($summary) > 1000 || preg_match('/[<>]/u', $summary)) { throw new InvalidArgumentException('Ringkasan wajib berupa teks, maksimal 1.000 karakter.'); }
        $hash = hash('sha256', LPM_Content::json(['revision' => $revision, 'production' => $expected_production, 'summary' => $summary,
            'actor' => get_current_user_id(), 'restore_id' => $restore_id, 'operation' => $restore_id ? 'restore' : 'publish']));
        $result = self::transaction($id, static function ($r) use ($revision, $expected_production, $summary, $key, $restore_id, $hash) {
            global $wpdb;
            $previous = self::row($wpdb->prepare('SELECT id,version_number,operation_payload_hash FROM ' . LPM_Schema::table('resource_versions') . ' WHERE resource_id = %d AND resource_type = %s AND operation_key = %s', $r['id'], $r['resource_type'], $key));
            if ($previous) {
                if (!hash_equals($previous['operation_payload_hash'], $hash)) { throw new LPM_Conflict('Operation key sudah digunakan dengan input berbeda.'); }
                $existing=self::version($r,(int)$previous['id']);
                return ['version_id' => (int) $previous['id'], 'version_number' => (int) $previous['version_number'], 'production' => (int) $r['production_version_id'], 'replayed' => true,
                    '_snapshot_hash'=>hash('sha256',$existing['snapshot_data']),'_schema'=>(int)$existing['snapshot_schema_version']];
            }
            self::check_revision($r, $revision);
            if ((int) $r['production_version_id'] !== $expected_production) { throw new LPM_Conflict('Production telah berubah. Tinjau ulang preview dan perbandingan.'); }
            if ($restore_id !== null) {
                if ($restore_id === $expected_production) { throw new InvalidArgumentException('Versi ini sudah menjadi production.'); }
                $source = self::version($r, $restore_id);
                LPM_Content::validate($r['resource_type'], self::snapshot($source), true, (int) $source['snapshot_schema_version']);
                $json = $source['snapshot_data']; $schema = (int) $source['snapshot_schema_version'];
            } else {
                if ($r['draft_data'] === null) { throw new InvalidArgumentException('Simpan draft sebelum publish.'); }
                if ((int) $r['draft_base_version_id'] !== $expected_production) { throw new LPM_Conflict('Draft stale. Simpan hasil rekonsiliasi sebelum publish.'); }
                $content = LPM_Content::validate($r['resource_type'], LPM_Content::decode($r['draft_data']), true);
                $json = LPM_Content::json($content); $schema = LPM_Content::schema($content);
            }
            $number = (int) $r['last_version_number'] + 1;
            $version = self::insert(LPM_Schema::table('resource_versions'), ['resource_type' => $r['resource_type'], 'resource_id' => $r['id'],
                'version_number' => $number, 'snapshot_data' => $json, 'snapshot_schema_version' => $schema, 'change_summary' => $summary,
                'published_by' => get_current_user_id(), 'published_at' => gmdate('Y-m-d H:i:s'), 'publication_type' => $restore_id ? 'restore' : 'publish',
                'restored_from_version_id' => $restore_id, 'operation_key' => $key, 'operation_payload_hash' => $hash]);
            self::fault('after_snapshot');
            $update = ['production_version_id' => $version, 'last_version_number' => $number,'updated_at'=>gmdate('Y-m-d H:i:s'),'updated_by'=>get_current_user_id()];
            if ($restore_id === null) {
                $update += ['draft_data' => null, 'draft_base_version_id' => null, 'draft_updated_at' => null, 'draft_updated_by' => null, 'draft_revision' => $revision + 1];
            }
            self::update($update, (int) $r['id']);
            self::fault('after_pointer');
            self::insert(LPM_Schema::table('cache_events'), ['resource_id' => $r['id'], 'resource_type' => $r['resource_type'], 'version_id' => $version,
                'created_at' => gmdate('Y-m-d H:i:s'), 'retry_at' => gmdate('Y-m-d H:i:s')]);
            self::fault('before_commit');
            return ['version_id' => $version, 'version_number' => $number, 'production' => $version, 'replayed' => false,
                '_snapshot_hash'=>hash('sha256',$json),'_schema'=>$schema];
        },static function($result,mysqli $observer)use($id,$key,$hash){
            global $wpdb;
            $r=LPM_Database::row($wpdb->prepare('SELECT * FROM '.LPM_Schema::table('resources').' WHERE id=%d',$id),$observer);
            $v=LPM_Database::row($wpdb->prepare('SELECT * FROM '.LPM_Schema::table('resource_versions').' WHERE id=%d AND resource_id=%d',$result['version_id'],$id),$observer);
            $active=$r&&$r['production_version_id']?LPM_Database::row($wpdb->prepare('SELECT * FROM '.LPM_Schema::table('resource_versions').' WHERE id=%d',$r['production_version_id']),$observer):null;
            $event=LPM_Database::scalar($wpdb->prepare('SELECT id FROM '.LPM_Schema::table('cache_events').' WHERE version_id=%d',$result['version_id']),$observer);
            if(!$r||!$v||!$active||!$event||$v['operation_key']!==$key||!hash_equals($v['operation_payload_hash'],$hash)||!hash_equals($result['_snapshot_hash'],hash('sha256',$v['snapshot_data']))||(int)$v['snapshot_schema_version']!==$result['_schema']||(int)$v['version_number']!==$result['version_number']||(int)$active['resource_id']!==$id||(int)$active['version_number']<$result['version_number']||(int)$r['last_version_number']<(int)$active['version_number']){
                throw new LPM_Persistence_Error('Publikasi belum terverifikasi setelah commit. Belum boleh dianggap berhasil; gunakan retry tindakan yang sama.');
            }
            unset($result['_snapshot_hash'],$result['_schema']);
            $r['shortcode']=LPM_Shortcode::make($r);
            $production_content=LPM_Content::upgrade($r['resource_type'],self::snapshot($active));
            $raw=$r['draft_data']!==null?LPM_Content::decode($r['draft_data']):$production_content;
            $base=$r['draft_data']!==null?(int)$r['draft_base_version_id']:(int)$r['production_version_id'];
            $result['editor']=['resource'=>$r,'content'=>LPM_Content::upgrade($r['resource_type'],$raw),'production_content'=>$production_content,
                'base'=>$base,'stale'=>$r['draft_data']!==null&&$base!==(int)$r['production_version_id'],'legacy'=>LPM_Content::schema($raw)===1,'cache_pending'=>0];
            $result['persisted']=true;$result['production']=(int)$r['production_version_id'];return $result;
        });
        // Database commit is complete; a cache failure must not be reported as a publish failure.
        try { LPM_Cache::process(); } catch (Throwable $e) { error_log('LPM cache worker unavailable; retry pending.'); }
        $result['cache_pending'] = LPM_Cache::pending($id);
        $result['editor']['cache_pending']=$result['cache_pending'];
        return $result;
    }

    /** Prepare an editable draft or withdraw a publication without deleting history. */
    public static function status(int $id,int $revision,int $production,string $target): array {
        if(!in_array($target,['draft','unpublish'],true))throw new InvalidArgumentException('Aksi status tidak valid.');
        $result=self::transaction($id,static function($r)use($revision,$production,$target){
            self::check_revision($r,$revision);
            if((int)$r['production_version_id']!==$production)throw new LPM_Conflict('Status berubah dari sesi lain. Muat ulang daftar sebelum mencoba lagi.');
            $data=$r['draft_data'];$changed=false;$update=[];
            if($data===null){
                $content=$production?LPM_Content::upgrade($r['resource_type'],self::snapshot(self::version($r,$production))):LPM_Content::initial($r['resource_type']);
                if(!$production)$content['title']=$r['name'];
                $data=LPM_Content::json($content);$changed=true;
                $update+=['draft_data'=>$data,'draft_base_version_id'=>$production?:null,'draft_updated_at'=>gmdate('Y-m-d H:i:s'),'draft_updated_by'=>get_current_user_id()];
            }
            if($target==='unpublish'&&$production){
                $changed=true;$update['production_version_id']=null;$update['draft_base_version_id']=null;
                global $wpdb;
                self::query($wpdb->prepare("INSERT INTO ".LPM_Schema::table('cache_events')." (resource_id,resource_type,version_id,event_type,created_at,retry_at) VALUES (%d,%s,%d,'unpublish',%s,%s) ON DUPLICATE KEY UPDATE completed_at=NULL,retry_at=VALUES(retry_at),attempts=0,last_error=NULL",
                    $r['id'],$r['resource_type'],$production,gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')));
            }
            if($changed){$update+=['draft_revision'=>$revision+1,'updated_at'=>gmdate('Y-m-d H:i:s'),'updated_by'=>get_current_user_id()];self::update($update,(int)$r['id']);}
            return ['revision'=>$revision+($changed?1:0),'production'=>$target==='unpublish'?0:$production,'draft_data'=>$data,'changed'=>$changed];
        },static function($result,mysqli $observer)use($id){
            global $wpdb;$r=LPM_Database::row($wpdb->prepare('SELECT draft_revision,production_version_id,draft_data FROM '.LPM_Schema::table('resources').' WHERE id=%d',$id),$observer);
            if(!$r||(int)$r['draft_revision']!==$result['revision']||(int)$r['production_version_id']!==$result['production']||$r['draft_data']!==$result['draft_data'])throw new LPM_Persistence_Error('Perubahan status belum terverifikasi. Muat ulang daftar untuk memeriksa hasil.');
            unset($result['draft_data']);return $result+['persisted'=>true];
        });
        if($target==='unpublish')try{LPM_Cache::process();}catch(Throwable $e){error_log('LPM unpublish cache retry pending: resource='.$id);}
        return $result;
    }

    /** Soft deletion keeps snapshots and draft for database recovery. */
    public static function delete(int $id,int $revision,int $production): array {
        $result=self::transaction($id,static function($r)use($revision,$production){
            if(!empty($r['deleted_at'])){return ['deleted'=>true];}
            self::check_revision($r,$revision);
            if((int)$r['production_version_id']!==$production){throw new LPM_Conflict('Tabel baru saja diperbarui. Periksa kembali sebelum menghapus.');}
            self::update(['deleted_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s'),'updated_by'=>get_current_user_id()],(int)$r['id']);
            if($production){
                // Reuse the version event so page-cache integrations receive a durable retry.
                global $wpdb;
                self::query($wpdb->prepare('INSERT INTO '.LPM_Schema::table('cache_events').' (resource_id,resource_type,version_id,created_at,retry_at) VALUES (%d,%s,%d,%s,%s) ON DUPLICATE KEY UPDATE completed_at=NULL,retry_at=VALUES(retry_at),attempts=0,last_error=NULL',
                    $r['id'],$r['resource_type'],$production,gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')));
            }
            return ['deleted'=>true];
        },static function($result,mysqli $observer)use($id){
            global $wpdb;
            $r=LPM_Database::row($wpdb->prepare('SELECT deleted_at FROM '.LPM_Schema::table('resources').' WHERE id=%d',$id),$observer);
            if(!$r||empty($r['deleted_at'])){throw new LPM_Persistence_Error('Penghapusan belum terkonfirmasi. Coba lagi.');}
            return $result+['persisted'=>true];
        },true);
        try{LPM_Cache::process();}catch(Throwable $e){error_log('LPM delete cache retry pending: resource='.$id);}
        return $result;
    }

    private static function fault(string $stage): void {
        if (defined('LPM_TESTING') && LPM_TESTING && is_callable(self::$test_fault)) { (self::$test_fault)($stage); }
    }

    public static function editor(int $id): array {
        global $wpdb;
        self::authorize();
        // Capture mutable resource once; immutable snapshots remain consistent with these pointers.
        $r = self::resource($id);
        $production = $r['production_version_id'] ? self::snapshot(self::version($r, (int) $r['production_version_id'])) : null;
        $base = $r['draft_data'] !== null ? (int) $r['draft_base_version_id'] : (int) $r['production_version_id'];
        $raw = $r['draft_data'] !== null ? LPM_Content::decode($r['draft_data']) : ($production ?? LPM_Content::initial($r['resource_type']));
        $r['shortcode']=LPM_Shortcode::make($r);
        $upgraded=LPM_Content::upgrade($r['resource_type'],$raw);
        if (!$r['production_version_id'] && $r['draft_data']===null) { $upgraded['title']=$r['name']; }
        return ['resource' => $r, 'content' => $upgraded, 'legacy' => LPM_Content::schema($raw)===1,
            'production_content' => $production ? LPM_Content::upgrade($r['resource_type'],$production) : null,
            'base_content' => $base ? LPM_Content::upgrade($r['resource_type'],self::snapshot(self::version($r, $base))) : null,
            'base' => $base, 'stale' => $r['draft_data'] !== null && $base !== (int) $r['production_version_id'], 'cache_pending' => LPM_Cache::pending($id),
            'cache_status' => LPM_Database::rows($wpdb->prepare('SELECT attempts,retry_at,last_error FROM ' . LPM_Schema::table('cache_events') . ' WHERE resource_id = %d AND completed_at IS NULL ORDER BY id ASC LIMIT 20', $id))];
    }

    public static function history(int $id, int $page = 1, int $limit = 20): array {
        global $wpdb;
        self::authorize(); $r = self::resource($id);
        $limit = max(1, min(100, $limit)); $page = max(1, $page);
        $rows = LPM_Database::rows($wpdb->prepare('SELECT id,version_number,published_at,published_by,change_summary,publication_type,restored_from_version_id FROM ' . LPM_Schema::table('resource_versions') . ' WHERE resource_id = %d AND resource_type = %s ORDER BY id DESC LIMIT %d OFFSET %d', $id, $r['resource_type'], $limit, ($page - 1) * $limit));
        foreach ($rows as &$row) {
            $row['status'] = (int) $row['id'] === (int) $r['production_version_id'] ? 'Production' : 'Archived';
            $user = get_userdata((int) $row['published_by']);
            $row['publisher'] = $user ? $user->display_name : 'User #' . $row['published_by'];
            $row['published_local'] = wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['published_at'] . ' UTC'));
        }
        unset($row);
        return $rows;
    }
}
