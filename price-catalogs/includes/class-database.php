<?php
defined('ABSPATH') || exit;

final class LPM_Persistence_Error extends RuntimeException {}

/** A primary connection owned by this plugin, never the shared WordPress transaction. */
final class LPM_Database {
    private static ?mysqli $primary=null;
    public static function open(): mysqli {
        global $wpdb;
        $db=null;
        try {
            $configured_host=str_starts_with(DB_HOST,'p:')?substr(DB_HOST,2):DB_HOST;
            $parts=$wpdb->parse_db_host($configured_host);
            if(!$parts){throw new RuntimeException('Unsupported database host.');}
            [$host,$port,$socket,$ipv6]=$parts;
            if($ipv6&&extension_loaded('mysqlnd')){$host='['.$host.']';}
            // Do not reuse a persistent/global connection or inherit its autocommit state.
            if(str_starts_with($host,'p:')){$host=substr($host,2);}
            $db=mysqli_init();mysqli_options($db,MYSQLI_OPT_CONNECT_TIMEOUT,10);
            $flags=defined('MYSQL_CLIENT_FLAGS')?MYSQL_CLIENT_FLAGS:0;
            if(!@mysqli_real_connect($db,$host,DB_USER,DB_PASSWORD,DB_NAME,$port,$socket,$flags)){throw new RuntimeException('Primary database unavailable.');}
            if(!mysqli_set_charset($db,$wpdb->charset?:'utf8mb4')||!mysqli_autocommit($db,true)){throw new RuntimeException('Database session could not be initialized.');}
            return $db;
        }catch(Throwable $e){
            if($db instanceof mysqli){try{mysqli_close($db);}catch(Throwable $ignored){}}
            throw new LPM_Persistence_Error('Koneksi utama penyimpanan belum dapat diverifikasi. Input tetap dipertahankan.',0,$e);
        }
    }
    public static function connection(): mysqli { return self::$primary??=self::open(); }
    public static function forget(mysqli $db): void {
        if(self::$primary===$db){self::$primary=null;}
        try{mysqli_close($db);}catch(Throwable $ignored){}
    }
    public static function execute(string $sql,?mysqli $db=null) {
        global $wpdb;$db??=self::connection();
        // prepare() leaves escaped percent placeholders for wpdb::query's filter.
        // Native mysqli must remove those placeholders explicitly.
        $sql=$wpdb->remove_placeholder_escape($sql);
        try{$result=mysqli_query($db,$sql);}
        catch(Throwable $e){self::forget($db);throw new LPM_Persistence_Error('Query penyimpanan gagal. Input tetap dipertahankan; ulangi publish dengan identitas operasi yang sama.',0,$e);}
        if($result===false){$code=mysqli_errno($db);if(in_array($code,[2006,2013],true)){self::forget($db);}throw new LPM_Persistence_Error('Query penyimpanan gagal. Input tetap dipertahankan; ulangi publish dengan identitas operasi yang sama.');}
        return $result;
    }
    public static function rows(string $sql,?mysqli $db=null): array {
        $result=self::execute($sql,$db);
        if(!$result instanceof mysqli_result){throw new LPM_Persistence_Error('Respons pembacaan database tidak valid.');}
        $rows=$result->fetch_all(MYSQLI_ASSOC);$result->free();return $rows;
    }
    public static function row(string $sql,?mysqli $db=null): ?array { return self::rows($sql,$db)[0]??null; }
    public static function scalar(string $sql,?mysqli $db=null) { $row=self::row($sql,$db);return $row?reset($row):null; }
}
