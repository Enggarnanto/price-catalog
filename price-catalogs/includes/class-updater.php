<?php
defined('ABSPATH') || exit;

/** Private HTTPS feed, integrated with WordPress's own plugin upgrader. */
final class LPM_Updater {
    public const OPTION='lpm_updater_settings';
    public const CACHE='lpm_updater_feed';
    public const DEFAULT_REPOSITORY='Enggarnanto/price-catalog';
    private const SLUG='price-catalogs';
    private static bool $loading=false;

    public static function plugin(): string { return plugin_basename(LPM_FILE); }
    public static function settings(): array {
        $value=get_site_option(self::OPTION,[]);if(!is_array($value))$value=[];
        $manifest=is_string($value['manifest_url']??null)?$value['manifest_url']:'';
        return ['enabled'=>!empty($value['enabled']),'source'=>($value['source']??($manifest?'manifest':'github'))==='manifest'?'manifest':'github','manifest_url'=>$manifest,'github_repository'=>(string)($value['github_repository']??self::DEFAULT_REPOSITORY)];
    }
    public static function repository(string $value): string {
        $value=trim($value);$value=preg_replace('#^https://github\.com/#i','',$value);$value=preg_replace('#(?:\.git)?/$#','',$value);$value=preg_replace('/\.git$/','',$value);
        if(!preg_match('#^[a-zA-Z0-9][a-zA-Z0-9-]{0,38}/[a-zA-Z0-9_.-]{1,100}$#D',$value)||in_array(explode('/',$value)[1],['.','..'],true))throw new InvalidArgumentException('Isi repository publik dalam format owner/repo atau URL https://github.com/owner/repo.');
        return $value;
    }
    public static function source_url(?array $settings=null): string {
        $settings??=self::settings();return $settings['source']==='github'?($settings['github_repository']?'https://api.github.com/repos/'.$settings['github_repository'].'/releases/latest':''):$settings['manifest_url'];
    }
    public static function can_manage(): bool {
        return current_user_can('manage_options')&&(!is_multisite()||current_user_can('manage_network_options'));
    }
    public static function page_url(): string {
        return is_multisite()?network_admin_url('admin.php?page=lpm-updates'):admin_url('admin.php?page=lpm-updates');
    }
    public static function boot(): void {
        add_action(is_multisite()?'network_admin_menu':'admin_menu',static function(){
            if(!self::can_manage())return;
            add_submenu_page(is_multisite()?'plugins.php':'lpm','Updater Pricelist','Updater',is_multisite()?'manage_network_options':'manage_options','lpm-updates',[self::class,'page']);
        },30);
        add_action('admin_enqueue_scripts',static function(){
            if(($_GET['page']??'')==='lpm-updates')wp_enqueue_style('lpm-updater',plugins_url('assets/updater.css',LPM_FILE),[],LPM_VERSION);
        });
        add_action('admin_post_lpm_updater_save',[self::class,'save_post']);
        add_action('admin_post_lpm_updater_check',[self::class,'check_post']);
        add_filter('pre_set_site_transient_update_plugins',static fn($value)=>self::inject($value,true));
        add_filter('site_transient_update_plugins',static fn($value)=>self::inject($value,false));
        add_filter('plugins_api',[self::class,'plugin_info'],20,3);
        add_filter('upgrader_pre_download',[self::class,'download'],999,4);
        add_action('upgrader_process_complete',static function($upgrader,$extra){
            if(($extra['type']??'')==='plugin'&&($extra['action']??'')==='update'&&in_array(self::plugin(),$extra['plugins']??[],true))delete_site_transient(self::CACHE);
        },10,2);
    }
    public static function url(string $url): string {
        $parts=wp_parse_url($url);
        if(!$parts||($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])||isset($parts['port'])&&(int)$parts['port']!==443||!wp_http_validate_url($url)){
            throw new InvalidArgumentException('Gunakan URL HTTPS publik tanpa username, password, fragment, atau port khusus.');
        }
        $host=trim($parts['host'],'[]');$ip=filter_var($host,FILTER_VALIDATE_IP)?$host:gethostbyname($host);
        if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new InvalidArgumentException('Sumber update harus memakai alamat internet publik, bukan localhost atau jaringan privat.');
        return $url;
    }
    public static function validate(array $data): array {
        if(($data['slug']??'')!==self::SLUG)throw new InvalidArgumentException('Slug manifest harus price-catalogs.');
        foreach(['version','requires','requires_php'] as $key){
            if(!is_string($data[$key]??null)||!preg_match('/^\d+\.\d+(?:\.\d+)?(?:-[0-9A-Za-z.-]+)?$/D',$data[$key]))throw new InvalidArgumentException('Versi manifest tidak valid: '.$key);
        }
        if(!is_string($data['sha256']??null)||!preg_match('/^[a-fA-F0-9]{64}$/D',$data['sha256']))throw new InvalidArgumentException('Manifest membutuhkan SHA-256 ZIP (64 karakter hex).');
        $download=self::url(is_string($data['download_url']??null)?$data['download_url']:'');
        if(isset($data['changelog'])&&(!is_string($data['changelog'])||strlen($data['changelog'])>65536))throw new InvalidArgumentException('Changelog terlalu besar atau tidak valid.');
        return ['slug'=>self::SLUG,'version'=>$data['version'],'requires'=>$data['requires'],'requires_php'=>$data['requires_php'],'download_url'=>$download,'sha256'=>strtolower($data['sha256']),'changelog'=>wp_kses_post($data['changelog']??'')];
    }
    private static function request(string $url,array $args=[]){
        for($redirect=0;$redirect<=3;$redirect++){
            try{self::url($url);}catch(Throwable $e){return new WP_Error('lpm_update_url',$e->getMessage());}
            $response=wp_safe_remote_get($url,array_merge(['timeout'=>15,'redirection'=>0,'sslverify'=>true],$args));
            if(is_wp_error($response))return new WP_Error('lpm_update_http','Sumber update belum dapat dihubungi. Periksa URL, koneksi, dan TLS hosting.');
            $code=wp_remote_retrieve_response_code($response);
            if(in_array($code,[301,302,303,307,308],true)){
                $location=wp_remote_retrieve_header($response,'location');
                if(!$location)return new WP_Error('lpm_update_redirect','Redirect update tidak memiliki tujuan.');
                $url=WP_Http::make_absolute_url($location,$url);continue;
            }
            if($code!==200)return new WP_Error('lpm_update_http','Sumber update mengembalikan HTTP '.$code.'.');
            return $response;
        }
        return new WP_Error('lpm_update_redirect','Terlalu banyak redirect pada sumber update.');
    }
    public static function cached(){
        $settings=self::settings();$cache=get_site_transient(self::CACHE);
        return $settings['enabled']&&is_array($cache)&&($cache['source']??'')===self::source_url($settings)?$cache:false;
    }
    private static function github(string $repository): array {
        $url='https://api.github.com/repos/'.$repository.'/releases/latest';
        $response=self::request($url,['limit_response_size'=>1048577,'headers'=>['Accept'=>'application/vnd.github+json','X-GitHub-Api-Version'=>'2022-11-28','User-Agent'=>'Latis-Price-Catalogs/'.LPM_VERSION]]);
        if(is_wp_error($response))throw new RuntimeException($response->get_error_message().' Pastikan repository publik dan sudah memiliki GitHub Release stabil.');
        $body=wp_remote_retrieve_body($response);if(strlen($body)>1048576)throw new InvalidArgumentException('Metadata GitHub Release terlalu besar.');
        $release=json_decode($body,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($release)||!empty($release['draft'])||!empty($release['prerelease'])||!is_string($release['tag_name']??null))throw new InvalidArgumentException('Updater membutuhkan GitHub Release stabil, bukan draft/prerelease.');
        $version=preg_replace('/^[vV]/','',$release['tag_name']);
        if(!preg_match('/^\d+\.\d+(?:\.\d+)?$/D',$version))throw new InvalidArgumentException('Tag GitHub Release harus berupa versi stabil, misalnya v2.3.1.');
        if(!is_array($release['assets']??null))throw new InvalidArgumentException('Daftar aset GitHub Release tidak valid.');
        $assets=[];foreach($release['assets'] as $asset)if(is_array($asset)&&($asset['state']??'uploaded')==='uploaded')$assets[$asset['name']??'']=$asset;
        $asset=$assets['latis-price-catalogs-'.$version.'.zip']??$assets['price-catalogs.zip']??null;
        if(!$asset)throw new InvalidArgumentException('Upload aset ZIP latis-price-catalogs-'.$version.'.zip atau price-catalogs.zip ke GitHub Release. Arsip Source code tidak dipakai.');
        $asset_url=static function(array $item)use($repository,$release):string{
            $url=$item['browser_download_url']??'';$prefix='https://github.com/'.$repository.'/releases/download/'.rawurlencode($release['tag_name']).'/';
            if(!is_string($url)||strncasecmp($url,$prefix,strlen($prefix))!==0)throw new InvalidArgumentException('URL aset tidak cocok dengan repository dan tag release.');
            return self::url($url);
        };
        $download=$asset_url($asset);$sha='';
        if(preg_match('/^sha256:([a-fA-F0-9]{64})$/D',(string)($asset['digest']??''),$match))$sha=strtolower($match[1]);
        if(isset($assets['price-catalogs-update.json'])){
            $meta=self::request($asset_url($assets['price-catalogs-update.json']),['limit_response_size'=>131073]);
            if(is_wp_error($meta))throw new RuntimeException($meta->get_error_message());
            $body=wp_remote_retrieve_body($meta);if(strlen($body)>131072)throw new InvalidArgumentException('Manifest release terlalu besar.');
            $data=json_decode($body,true,32,JSON_THROW_ON_ERROR);if(!is_array($data))throw new InvalidArgumentException('Manifest release harus berupa objek JSON.');$data=self::validate($data);
            if($data['version']!==$version||$data['download_url']!==$download||($sha&&$data['sha256']!==$sha))throw new InvalidArgumentException('Manifest tidak cocok dengan versi, ZIP atau digest GitHub Release.');
            return $data;
        }
        if(!$sha&&isset($assets[$asset['name'].'.sha256'])){
            $sum=self::request($asset_url($assets[$asset['name'].'.sha256']),['limit_response_size'=>4097]);if(is_wp_error($sum))throw new RuntimeException($sum->get_error_message());
            if(preg_match('/^([a-fA-F0-9]{64})(?:[ \t]+\*?'.preg_quote($asset['name'],'/').')?[\r\n]*$/D',trim(wp_remote_retrieve_body($sum)),$match))$sha=strtolower($match[1]);
        }
        if(!$sha)throw new InvalidArgumentException('ZIP release belum memiliki digest SHA-256. Upload juga '.$asset['name'].'.sha256 atau price-catalogs-update.json.');
        $data=self::validate(['slug'=>self::SLUG,'version'=>$version,'download_url'=>$download,'sha256'=>$sha,'requires'=>'6.2','requires_php'=>'8.1','changelog'=>nl2br(esc_html(mb_substr((string)($release['body']??''),0,32000)))]);
        $data['requirements_from_zip']=true;return $data;
    }
    public static function check(bool $force=false){
        $settings=self::settings();
        $source=self::source_url($settings);if(!$settings['enabled']||!$source)return new WP_Error('lpm_update_disabled','Aktifkan updater dan isi repository GitHub atau URL manifest terlebih dahulu.');
        if(!$force&&($cache=self::cached()))return !empty($cache['error'])?new WP_Error('lpm_update_feed',$cache['error']):$cache['manifest'];
        if(self::$loading)return new WP_Error('lpm_update_busy','Pemeriksaan update sedang berjalan.');
        self::$loading=true;$error='';$manifest=null;
        try{
            if($settings['source']==='github'){$manifest=self::github($settings['github_repository']);}
            else{
            $response=self::request($source,['limit_response_size'=>131073,'headers'=>['Accept'=>'application/json']]);
            if(is_wp_error($response))throw new RuntimeException($response->get_error_message());
            $body=wp_remote_retrieve_body($response);
            if(strlen($body)>131072)throw new InvalidArgumentException('Manifest maksimal 128 KiB.');
            $data=json_decode($body,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($data))throw new InvalidArgumentException('Isi manifest harus objek JSON.');
            $manifest=self::validate($data);
            }
        }catch(Throwable $e){$error=$e instanceof JsonException?'JSON sumber update tidak terbaca.':$e->getMessage();}
        finally{self::$loading=false;}
        $cache=['source'=>$source,'checked_at'=>time(),'manifest'=>$manifest,'error'=>$error];
        set_site_transient(self::CACHE,$cache,$error?10*MINUTE_IN_SECONDS:6*HOUR_IN_SECONDS);
        update_site_option('lpm_updater_last_check',$cache);
        return $error?new WP_Error('lpm_update_feed',$error):$manifest;
    }
    public static function compatible(array $data): bool {
        global $wp_version;
        return version_compare(PHP_VERSION,$data['requires_php'],'>=')&&version_compare($wp_version,$data['requires'],'>=');
    }
    public static function inject($value,bool $fetch=false){
        if(!is_object($value))return $value;
        $key=self::plugin();unset($value->response[$key],$value->no_update[$key]);
        $settings=self::settings();if(!$settings['enabled']||!self::source_url($settings))return $value;
        if($fetch)self::check();
        $cache=self::cached();if(!$cache||!empty($cache['error'])||empty($cache['manifest']))return $value;
        $data=$cache['manifest'];$item=(object)['id'=>'https://www.mtalkblog.com/p/plugin-price-catalogs.html','slug'=>self::SLUG,'plugin'=>$key,'new_version'=>$data['version'],'url'=>'https://www.mtalkblog.com/p/plugin-price-catalogs.html','package'=>self::compatible($data)?$data['download_url']:'','requires'=>$data['requires'],'requires_php'=>$data['requires_php']];
        if(version_compare($data['version'],LPM_VERSION,'>')){$value->response[$key]=$item;}else{$value->no_update[$key]=$item;}
        return $value;
    }
    public static function plugin_info($result,string $action,$args){
        if($action!=='plugin_information'||($args->slug??'')!==self::SLUG)return $result;
        $data=self::check();if(is_wp_error($data))return $data;
        return (object)['name'=>'Latis Price Catalogs','slug'=>self::SLUG,'version'=>$data['version'],'author'=>'Galih Enggarnanto','homepage'=>'https://www.mtalkblog.com/p/plugin-price-catalogs.html','requires'=>$data['requires'],'requires_php'=>$data['requires_php'],'download_link'=>self::compatible($data)?$data['download_url']:'','sections'=>['description'=>'Tabel harga dan katalog gambar terpusat untuk WordPress.','changelog'=>$data['changelog']]];
    }
    public static function verify_zip(string $file,array $data): void {
        if(!is_file($file)||!hash_equals($data['sha256'],hash_file('sha256',$file)))throw new RuntimeException('Checksum ZIP tidak cocok. Paket update dibatalkan.');
        if(!class_exists('ZipArchive'))throw new RuntimeException('Ekstensi PHP ZipArchive dibutuhkan untuk memverifikasi paket update.');
        $zip=new ZipArchive();if($zip->open($file)!==true)throw new RuntimeException('Paket update bukan ZIP yang valid.');
        try{
            if($zip->numFiles>10000)throw new RuntimeException('Paket update memiliki terlalu banyak berkas.');
            $size=0;
            for($i=0;$i<$zip->numFiles;$i++){
                $entry=$zip->statIndex($i);$name=$entry['name'];$size+=$entry['size'];
                if(!str_starts_with($name,'price-catalogs/')||str_contains($name,'\\')||str_contains($name,':')||str_contains($name,"\0")||preg_match('#(?:^|/)\.\.(?:/|$)#',$name)||$size>104857600)throw new RuntimeException('Struktur ZIP update tidak aman atau melebihi batas.');
                if($zip->getExternalAttributesIndex($i,$opsys,$attributes)&&(($attributes>>16)&0170000)===0120000)throw new RuntimeException('ZIP update tidak boleh berisi symbolic link.');
            }
            $stat=$zip->statName('price-catalogs/price-catalogs.php');
            if(!$stat||$stat['size']>65536)throw new RuntimeException('File utama plugin tidak ditemukan pada root price-catalogs.');
            $header=$zip->getFromName('price-catalogs/price-catalogs.php');
            if(!preg_match('/^[\t \/*#@]*Plugin Name\s*:\s*(.+)$/mi',$header,$name)||trim($name[1])!=='Latis Price Catalogs')throw new RuntimeException('Nama plugin dalam ZIP tidak sesuai.');
            if(!preg_match('/^[\t \/*#@]*Version\s*:\s*(.+)$/mi',$header,$match)||trim($match[1])!==$data['version'])throw new RuntimeException('Versi di dalam ZIP tidak sesuai manifest.');
            global $wp_version;
            foreach(['Requires at least'=>$wp_version,'Requires PHP'=>PHP_VERSION] as $field=>$current){if(preg_match('/^[\t \/*#@]*'.preg_quote($field,'/').'\s*:\s*(.+)$/mi',$header,$required)&&version_compare($current,trim($required[1]),'<'))throw new RuntimeException('Paket membutuhkan '.$field.' '.trim($required[1]).' atau lebih baru.');}
        }finally{$zip->close();}
    }
    public static function download($reply,string $package,$upgrader,array $extra=[]){
        if(($extra['plugin']??'')!==self::plugin()||is_wp_error($reply))return $reply;
        $data=self::check();if(is_wp_error($data))return $data;
        if($package!==$data['download_url']||!self::compatible($data)||!version_compare($data['version'],LPM_VERSION,'>'))return new WP_Error('lpm_update_package','Paket, versi, atau persyaratan update sudah berubah. Cek update kembali.');
        $owned=$reply===false;$file=$owned?wp_tempnam('lpm-update.zip'):$reply;
        if(!is_string($file)||!$file)return new WP_Error('lpm_update_temp','Berkas sementara update belum dapat dibuat.');
        try{
            if($owned){$response=self::request($package,['timeout'=>60,'stream'=>true,'filename'=>$file,'limit_response_size'=>52428801]);if(is_wp_error($response))throw new RuntimeException($response->get_error_message());if(filesize($file)>52428800)throw new RuntimeException('ZIP update maksimal 50 MiB.');}
            self::verify_zip($file,$data);return $file;
        }catch(Throwable $e){if($owned)wp_delete_file($file);return new WP_Error('lpm_update_package',$e->getMessage());}
    }
    public static function refresh(){
        $result=self::check(true);$value=get_site_transient('update_plugins');
        if(!is_object($value))$value=new stdClass();
        $value->checked[self::plugin()]=LPM_VERSION;$value->last_checked=time();set_site_transient('update_plugins',self::inject($value,false));return $result;
    }
    public static function save_settings(array $input): void {
        if(!self::can_manage())throw new RuntimeException('Akses updater ditolak.',403);
        $url=trim((string)($input['manifest_url']??''));$enabled=!empty($input['enabled']);$source=$input['source']??(array_key_exists('manifest_url',$input)?'manifest':'github');
        if(!in_array($source,['manifest','github'],true))throw new InvalidArgumentException('Sumber updater tidak valid.');
        $repository=trim((string)($input['github_repository']??''));if($repository!=='')$repository=self::repository($repository);
        if($url!=='')self::url($url);if($enabled&&($source==='github'?$repository==='':$url===''))throw new InvalidArgumentException('Isi repository GitHub atau URL manifest untuk sumber yang dipilih.');
        update_site_option(self::OPTION,['enabled'=>$enabled,'source'=>$source,'manifest_url'=>$url,'github_repository'=>$repository]);delete_site_transient(self::CACHE);delete_site_option('lpm_updater_last_check');
        $value=get_site_transient('update_plugins');if(is_object($value)){unset($value->response[self::plugin()],$value->no_update[self::plugin()]);set_site_transient('update_plugins',$value);}
    }
    public static function save_post(): void {
        if(!self::can_manage())wp_die('Akses updater ditolak.',403);check_admin_referer('lpm_updater_save');
        try{self::save_settings(wp_unslash($_POST));$notice='saved';}catch(Throwable $e){set_transient('lpm_updater_error_'.get_current_user_id(),$e->getMessage(),MINUTE_IN_SECONDS);$notice='error';}
        wp_safe_redirect(add_query_arg('notice',$notice,self::page_url()));exit;
    }
    public static function check_post(): void {
        if(!self::can_manage())wp_die('Akses updater ditolak.',403);check_admin_referer('lpm_updater_check');self::refresh();wp_safe_redirect(add_query_arg('notice','checked',self::page_url()));exit;
    }
    public static function page(): void {
        if(!self::can_manage())wp_die('Akses updater ditolak.',403);
        $settings=self::settings();$last=self::cached();$fresh=$last!==false;if(!$last){$last=get_site_option('lpm_updater_last_check',[]);if(($last['source']??'')!==self::source_url($settings))$last=[];}
        $data=$last['manifest']??null;$error=$last['error']??'';$new=$data&&version_compare($data['version'],LPM_VERSION,'>');$compatible=$data&&self::compatible($data);
        $title=!$settings['enabled']?'Updater belum aktif':($error?'Pemeriksaan belum berhasil':(!$data?'Belum diperiksa':(!$fresh?'Perlu cek update kembali':($new?'Update tersedia':'Versi terpasang sudah terbaru'))));
        $description=!$settings['enabled']?'Repository GitHub sudah disiapkan. Aktifkan pemeriksaan update, lalu cek rilis terbaru.':($error?:(!$fresh||!$data?'Klik Cek update sekarang untuk mengambil informasi rilis terbaru.':($compatible?'Pembaruan dipasang melalui updater bawaan WordPress.':'Rilis ini membutuhkan WordPress '.$data['requires'].' dan PHP '.$data['requires_php'].' atau lebih baru.')));
        if(!empty($data['requirements_from_zip']))$description.=' Persyaratan rilis diperiksa lagi dari header ZIP sebelum pemasangan.';
        echo '<div class="wrap lpm-updater"><div class="lpm-update-heading"><div><p class="lpm-update-eyebrow">PRICELIST / PENGATURAN</p><h1>Updater plugin</h1><p>Kelola sumber rilis Latis Price Catalogs.</p></div><span class="lpm-update-version">v'.esc_html(LPM_VERSION).'</span></div>';
        if(($_GET['notice']??'')==='saved')echo '<div class="notice notice-success"><p>Pengaturan updater tersimpan.</p></div>';
        if(($_GET['notice']??'')==='error'){$message=get_transient('lpm_updater_error_'.get_current_user_id());delete_transient('lpm_updater_error_'.get_current_user_id());echo '<div class="notice notice-error"><p>'.esc_html($message?:'Pengaturan belum dapat disimpan.').'</p></div>';}
        echo '<section class="lpm-update-card"><h2>'.esc_html($title).'</h2><p role="status">'.esc_html($description).'</p><dl><div><dt>Versi terpasang</dt><dd>'.esc_html(LPM_VERSION).'</dd></div><div><dt>Versi di sumber</dt><dd>'.esc_html($data['version']??'—').'</dd></div><div><dt>Terakhir diperiksa</dt><dd>'.(!empty($last['checked_at'])?esc_html(wp_date('d M Y H:i',$last['checked_at'])):'Belum pernah').'</dd></div><div><dt>Pembuat</dt><dd>Galih Enggarnanto</dd></div></dl><div class="lpm-update-actions"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('lpm_updater_check');
        echo '<input type="hidden" name="action" value="lpm_updater_check"><button class="button button-secondary" '.disabled(!$settings['enabled']||!self::source_url($settings),true,false).'>Cek update sekarang</button></form>';
        if($fresh&&$settings['enabled']&&!$error&&$new&&$compatible&&current_user_can('update_plugins')&&wp_is_file_mod_allowed('lpm_updater')){
            $url=wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin='.rawurlencode(self::plugin())),'upgrade-plugin_'.self::plugin());echo '<a class="button button-primary" href="'.esc_url($url).'">Update ke v'.esc_html($data['version']).'</a>';
        }
        echo '</div></section><section class="lpm-update-card"><h2>Sumber update</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('lpm_updater_save');
        echo '<input type="hidden" name="action" value="lpm_updater_save"><label class="lpm-update-toggle"><input type="checkbox" name="enabled" value="1" '.checked($settings['enabled'],true,false).'> Aktifkan pemeriksaan update dari sumber ini</label><label class="lpm-update-field">Sumber rilis<select name="source"><option value="github" '.selected($settings['source'],'github',false).'>GitHub Releases (repository publik)</option><option value="manifest" '.selected($settings['source'],'manifest',false).'>URL manifest JSON</option></select></label><label class="lpm-update-field">Repository GitHub<input type="text" name="github_repository" placeholder="owner/repo" value="'.esc_attr($settings['github_repository']).'"></label><p class="description">Untuk GitHub, cukup isi owner/repo. Updater mengambil release stabil terbaru dan aset ZIP plugin; tidak memakai arsip Source code.</p><label class="lpm-update-field">URL manifest JSON (untuk sumber alternatif)<input type="url" name="manifest_url" placeholder="https://domain-lo.com/updates/price-catalogs.json" value="'.esc_attr($settings['manifest_url']).'"></label><p class="description">Pilih salah satu sumber di atas. Checksum ZIP wajib tersedia; pengaturan berlaku untuk seluruh network pada multisite.</p><p><button class="button button-primary">Simpan pengaturan</button></p></form></section>';
        echo '<section class="lpm-update-card"><h2>Cara menerbitkan GitHub Release</h2><ol><li>Buat release stabil dengan tag v'.esc_html(LPM_VERSION).', lalu upload latis-price-catalogs-'.esc_html(LPM_VERSION).'.zip sebagai aset release.</li><li>Upload juga price-catalogs-update.json atau file .sha256 pendamping. GitHub asset digest SHA-256 juga dapat digunakan.</li><li>Aktifkan updater di sini, pilih GitHub Releases, simpan, lalu klik Cek update sekarang.</li><li>Jika versi release lebih baru, klik Update untuk membuka updater bawaan WordPress. Data tabel/katalog dan riwayat tetap di database.</li></ol><p>Pemeriksaan berkala mengikuti jadwal WordPress dengan cache maksimal 6 jam. Auto-install tidak diaktifkan oleh plugin ini; pengaturannya tersedia di halaman Plugins WordPress.</p><p>Repository harus publik dan sudah memiliki release stabil. Dukungan private repository/token belum tersedia. Update manual lewat upload ZIP tetap dapat dipakai.</p><details><summary>Contoh manifest release (opsional untuk GitHub)</summary><pre>'.esc_html(wp_json_encode(['slug'=>self::SLUG,'version'=>LPM_VERSION,'download_url'=>'https://github.com/'.($settings['github_repository']?:self::DEFAULT_REPOSITORY).'/releases/download/v'.LPM_VERSION.'/latis-price-catalogs-'.LPM_VERSION.'.zip','requires'=>'6.2','requires_php'=>'8.1','sha256'=>'GANTI_DENGAN_SHA256_ZIP_RILIS','changelog'=>'Catatan perubahan rilis.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</pre></details></section>';
        if($settings['enabled']&&$data&&!$error&&$data['changelog'])echo '<section class="lpm-update-card"><h2>Catatan rilis v'.esc_html($data['version']).'</h2>'.wp_kses_post($data['changelog']).'</section>';
        echo '</div>';
    }
}
