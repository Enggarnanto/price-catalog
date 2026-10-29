<?php
defined('ABSPATH') || exit;

final class LPM_Admin {
    public static function logo_url(): string {
        foreach (['logo.png','logo.webp','logo.jpg','logo.jpeg','logo.svg'] as $name) {
            if (is_file(dirname(LPM_FILE).'/assets/'.$name)) {
                return plugins_url('assets/'.$name,LPM_FILE);
            }
        }
        return '';
    }

    public static function boot(): void {
        add_action('admin_menu', static function () {
            // A full-resolution PNG has no intrinsic menu-size constraint in WP.
            // The SVG wrapper itself is 20px even before our scoped CSS loads.
            $library_cap=current_user_can('edit_posts')?'edit_posts':'edit_pages';
            add_menu_page('Pricelist', 'Pricelist', $library_cap, 'lpm', [self::class, 'page'], self::logo_url() ? plugins_url('assets/menu-icon.svg',LPM_FILE) : 'dashicons-tag', 58);
            foreach ([['lpm','All Tables',$library_cap],['lpm-new-table','Add New Table','manage_options'],['lpm-import','Import CSV','manage_options'],['lpm-catalogs','All Catalogs',$library_cap],['lpm-new-catalog','Add New Catalog','manage_options'],['lpm-styling','Styling','manage_options']] as [$slug,$label,$cap]) {
                add_submenu_page('lpm',$label,$label,$cap,$slug,[self::class,'page']);
            }
        });
        add_action('admin_enqueue_scripts', static function ($hook) {
            // The sidebar exists on every admin screen, including Dashboard.
            wp_enqueue_style('lpm-menu', plugins_url('assets/admin-menu.css', LPM_FILE), [], LPM_VERSION);
            if (!in_array(sanitize_key($_GET['page']??''),['lpm','lpm-new-table','lpm-import','lpm-catalogs','lpm-new-catalog','lpm-styling'],true)) { return; }
            wp_enqueue_media();
            wp_enqueue_style('lpm-public', plugins_url('assets/public.css', LPM_FILE), [], LPM_VERSION);
            wp_enqueue_style('lpm-admin', plugins_url('assets/admin.css', LPM_FILE), [], LPM_VERSION);
            wp_enqueue_style('lpm-editor', plugins_url('assets/editor.css', LPM_FILE), ['lpm-admin'], LPM_VERSION);
            wp_enqueue_script('lpm-carousel',plugins_url('assets/carousel.js',LPM_FILE),[],LPM_VERSION,true);
            wp_enqueue_script('lpm-grid-tools',plugins_url('assets/grid-tools.js',LPM_FILE),[],LPM_VERSION,true);
            wp_enqueue_script('lpm-export-tools',plugins_url('assets/export-tools.js',LPM_FILE),[],LPM_VERSION,true);
            wp_enqueue_script('lpm-admin', plugins_url('assets/admin.js', LPM_FILE), ['jquery', 'media-editor','lpm-carousel','lpm-grid-tools','lpm-export-tools'], LPM_VERSION, true);
            wp_localize_script('lpm-admin', 'LPM', ['root' => esc_url_raw(rest_url('lpm/v1')), 'nonce' => wp_create_nonce('wp_rest'),
                'canManage'=>current_user_can('manage_options'),'screen'=>sanitize_key($_GET['page']??'lpm'),'styling'=>LPM_Styling::get(),'logo'=>self::logo_url(),'version'=>LPM_VERSION,'author'=>'Galih Enggarnanto',
                'publicCss'=>plugins_url('assets/public.css',LPM_FILE),'publicScript'=>plugins_url('assets/carousel.js',LPM_FILE),
                'updaterUrl'=>LPM_Updater::can_manage()?LPM_Updater::page_url():'']);
        });
        add_action('rest_api_init', [self::class, 'routes']);
        add_filter('rest_post_dispatch', static function ($response, $server, $request) {
            if (str_starts_with($request->get_route(), '/lpm/v1/')) {
                $response->header('Cache-Control', 'no-store, private, max-age=0');
                $response->header('X-Robots-Tag', 'noindex, nofollow');
            }
            return $response;
        }, 10, 3);
    }

    public static function permission(WP_REST_Request $request) {
        $read = $request->get_method()==='GET' && (bool)preg_match('#^/lpm/v1/resources(?:$|/\d+/(published|usage)$)#',$request->get_route());
        if (!($read?(current_user_can('edit_posts')||current_user_can('edit_pages')):current_user_can('manage_options')) || !wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new WP_Error('lpm_forbidden', 'Akses atau nonce tidak valid.', ['status' => 403]);
        }
        return true;
    }

    public static function routes(): void {
        foreach ([['/resources', 'GET,POST', 'collection'], ['/resources/(?P<id>\d+)', 'GET', 'get'],
            ['/resources/(?P<id>\d+)/save', 'POST', 'save'], ['/resources/(?P<id>\d+)/review', 'GET', 'review'],
            ['/resources/(?P<id>\d+)/publish', 'POST', 'publish'], ['/resources/(?P<id>\d+)/history', 'GET', 'history'],
            ['/resources/(?P<id>\d+)/usage','GET','usage'], ['/resources/(?P<id>\d+)/published','GET','published'],
            ['/styling','GET,POST','styling'], ['/preview','POST','preview'], ['/csv','POST','csv'], ['/usage/rebuild','POST','rebuild'],
            ['/cache/retry', 'POST', 'retry'], ['/resources/(?P<id>\d+)/status','POST','status'], ['/resources/(?P<id>\d+)/delete','POST','delete']] as [$route, $method, $handler]) {
            register_rest_route('lpm/v1', $route, ['methods' => $method, 'permission_callback' => [self::class, 'permission'],
                'callback' => static function (WP_REST_Request $request) use ($handler) {
                    try {
                        if (strlen((string) $request->get_body()) > ($handler==='csv'?12582912:LPM_Content::MAX_BYTES+32768)) { throw new InvalidArgumentException('Payload terlalu besar.'); }
                        return rest_ensure_response(self::$handler($request));
                    } catch (Throwable $e) {
                        $status = $e instanceof LPM_Persistence_Error ? 503 : ($e instanceof LPM_Conflict ? 409 : (($e instanceof InvalidArgumentException || $e instanceof JsonException) ? 422 : ($e->getCode() === 403 ? 403 : 500)));
                        return new WP_Error($e instanceof LPM_Persistence_Error?'lpm_persistence_unverified':'lpm_error', $status === 500 ? 'Operasi belum dapat dipastikan. Muat ulang status atau ulangi dengan operation key yang sama.' : $e->getMessage(), ['status' => $status]);
                    }
                }]);
        }
    }

    private static function integer(WP_REST_Request $r, string $key, int $default = 0): int {
        $value = $r->get_param($key) ?? $default;
        if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) || (int) $value < 0 || (string) (int) $value !== (string) $value) {
            throw new InvalidArgumentException('Parameter angka tidak valid: ' . $key);
        }
        return (int) $value;
    }

    public static function collection(WP_REST_Request $r): array {
        global $wpdb;
        if ($r->get_method() === 'POST') {
            return ['id' => LPM_Service::create((string) $r['type'], (string) $r['name'])];
        }
        $page = max(1, self::integer($r, 'page', 1));
        $admin=current_user_can('manage_options');$table=LPM_Schema::table('resources');$versions=LPM_Schema::table('resource_versions');
        $where=['r.deleted_at IS NULL'];$params=[];
        if(!$admin){$where[]='r.production_version_id IS NOT NULL';}
        $mode="COALESCE(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(v.snapshot_data,r.draft_data),'$.mode')),CASE WHEN JSON_LENGTH(JSON_EXTRACT(COALESCE(v.snapshot_data,r.draft_data),'$.items'))>1 THEN 'carousel' ELSE 'single' END)";
        foreach(['type'=>'r.resource_type','program'=>"COALESCE(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(v.snapshot_data,r.draft_data),'$.program')),'')",'mode'=>$mode] as $key=>$column){
            if($r->get_param($key)!==null && $r->get_param($key)!==''){$where[]="$column=%s";$params[]=(string)$r[$key];}
        }
        if($r['search']){$like='%'.$wpdb->esc_like((string)$r['search']).'%';$where[]='(r.name LIKE %s OR r.description LIKE %s OR CAST(r.id AS CHAR)=%s)';array_push($params,$like,$like,(string)$r['search']);}
        if($r['status']==='published'){$where[]='r.production_version_id IS NOT NULL';}elseif($r['status']==='unpublished'){$where[]='r.production_version_id IS NULL';}
        if($admin&&$r['draft']==='yes'){$where[]='r.draft_data IS NOT NULL';}elseif($admin&&$r['draft']==='no'){$where[]='r.draft_data IS NULL';}
        $from=" FROM $table r LEFT JOIN $versions v ON r.production_version_id=v.id WHERE ".implode(' AND ',$where);
        $count=(int)LPM_Database::scalar($params?$wpdb->prepare('SELECT COUNT(*)'.$from,$params):'SELECT COUNT(*)'.$from);
        $columns="r.id,r.name,r.description,r.resource_type,r.production_version_id,COALESCE(r.updated_at,r.draft_updated_at,v.published_at) AS updated_at,COALESCE(r.updated_by,r.draft_updated_by,v.published_by) AS updated_by,r.last_version_number,v.published_at,JSON_UNQUOTE(JSON_EXTRACT(COALESCE(v.snapshot_data,r.draft_data),'$.program')) AS program,$mode AS mode,JSON_LENGTH(JSON_EXTRACT(COALESCE(v.snapshot_data,r.draft_data),'$.items')) AS image_count";
        if($admin){$columns.=",r.draft_revision,r.draft_data IS NOT NULL AS has_draft,r.draft_base_version_id,JSON_UNQUOTE(JSON_EXTRACT(r.draft_data,'$.program')) AS draft_program";}
        $query='SELECT '.$columns.$from.' ORDER BY COALESCE(r.updated_at,v.published_at,r.draft_updated_at) DESC,r.id DESC LIMIT 20 OFFSET %d';
        $rows=LPM_Database::rows($wpdb->prepare($query,array_merge($params,[($page-1)*20])));
        foreach($rows as &$row){$row['shortcode']=LPM_Shortcode::make($row);$row['usage_count']=LPM_Usage::posts((int)$row['id'],$row['resource_type'])['total'];$row['updated_local']=$row['updated_at']?wp_date(get_option('date_format').' H:i',strtotime($row['updated_at'].' UTC')):'-';$row['updater']=get_the_author_meta('display_name',(int)$row['updated_by']);}unset($row);
        return ['items'=>$rows,'total'=>$count,'page'=>$page,'index'=>get_option('lpm_usage_index',['complete'=>false])];
    }

    public static function get(WP_REST_Request $r): array { return LPM_Service::editor(self::integer($r, 'id')); }

    public static function preview(WP_REST_Request $r): array {
        $type=(string)$r['type'];$content=LPM_Content::validate($type,$r['content']);
        return ['html'=>LPM_Renderer::render($type,$content)];
    }

    public static function status(WP_REST_Request $r): array {
        return LPM_Service::status(self::integer($r,'id'),self::integer($r,'revision'),self::integer($r,'production'),(string)$r['target']);
    }

    public static function delete(WP_REST_Request $r): array {
        return LPM_Service::delete(self::integer($r,'id'),self::integer($r,'revision'),self::integer($r,'production'));
    }

    public static function save(WP_REST_Request $r): array {
        $reconcile = $r->get_param('reconcile') === true;
        if ($reconcile) {
            if (self::integer($r, 'source') !== 0) { throw new InvalidArgumentException('Rekonsiliasi harus meninjau draft.'); }
            self::verify_review($r);
        }
        if (!is_array($r['content'])) { throw new InvalidArgumentException('Konten tidak valid.'); }
        return LPM_Service::save(self::integer($r, 'id'), $r['content'], self::integer($r, 'revision'), self::integer($r, 'base'), $reconcile,
            $reconcile ? self::integer($r, 'production') : null, is_array($r['metadata'])?$r['metadata']:null);
    }

    private static function review_signature(int $id, int $revision, int $production, int $source): string {
        return hash_hmac('sha256', implode(':', [$id, $revision, $production, $source, get_current_user_id()]), wp_salt('nonce'));
    }

    private static function verify_review(WP_REST_Request $r): void {
        $expected = self::review_signature(self::integer($r, 'id'), self::integer($r, 'revision'), self::integer($r, 'production'), self::integer($r, 'source'));
        if (!is_string($r['review_token']) || !hash_equals($expected, $r['review_token'])) { throw new LPM_Conflict('Buka preview dan perbandingan sebelum melanjutkan.'); }
    }

    public static function review(WP_REST_Request $r): array {
        $editor = LPM_Service::editor(self::integer($r, 'id')); $resource = $editor['resource'];
        $source = self::integer($r, 'source');
        if ($source) {
            $version = LPM_Service::version($resource, $source);
            $content = LPM_Service::snapshot($version);
        } else { $content = $editor['content']; }
        return ['html' => LPM_Renderer::render($resource['resource_type'], $content), 'content' => $content,
            'base_html'=>$editor['base_content']?LPM_Renderer::render($resource['resource_type'],$editor['base_content']):'<p>Belum ada versi dasar.</p>',
            'production_html'=>$editor['production_content']?LPM_Renderer::render($resource['resource_type'],$editor['production_content']):'<p>Belum ada production.</p>',
            'base_content' => $editor['base_content'], 'production_content' => $editor['production_content'],
            'changes' => LPM_Content::diff($editor['production_content'] ?? [], $content),
            'base_changes' => LPM_Content::diff($editor['base_content'] ?? [], $content),
            'revision' => (int) $resource['draft_revision'], 'production' => (int) $resource['production_version_id'], 'source' => $source,
            'review_token' => self::review_signature((int) $resource['id'], (int) $resource['draft_revision'], (int) $resource['production_version_id'], $source)];
    }

    public static function publish(WP_REST_Request $r): array {
        self::verify_review($r);
        return LPM_Service::publish(self::integer($r, 'id'), self::integer($r, 'revision'), self::integer($r, 'production'), (string) $r['summary'], (string) $r['operation_key'], self::integer($r, 'source') ?: null);
    }

    public static function history(WP_REST_Request $r): array {
        return LPM_Service::history(self::integer($r, 'id'), self::integer($r, 'page', 1));
    }

    public static function retry(WP_REST_Request $r): array { LPM_Cache::process(true); return ['ok' => true]; }
    public static function styling(WP_REST_Request $r): array { return $r->get_method()==='POST'?LPM_Styling::save((array)$r['values']):LPM_Styling::get(); }
    public static function csv(WP_REST_Request $r): array { return LPM_CSV::parse((string)$r['csv'],(string)($r['delimiter']??','),$r['header']!==false); }
    public static function rebuild(WP_REST_Request $r): array { return LPM_Usage::step($r['reset']===true); }
    public static function published(WP_REST_Request $r): array {
        $resource=LPM_Service::resource(self::integer($r,'id'));
        if(!$resource['production_version_id']){throw new InvalidArgumentException('Resource belum dipublikasikan.');}
        return ['html'=>LPM_Renderer::render($resource['resource_type'],LPM_Service::snapshot(LPM_Service::version($resource,(int)$resource['production_version_id'])))];
    }
    public static function usage(WP_REST_Request $r): array {
        global $wpdb;$resource=LPM_Service::resource(self::integer($r,'id'));
        if(!current_user_can('manage_options')&&!$resource['production_version_id']){throw new RuntimeException('Akses ditolak.',403);}
        $result=LPM_Usage::posts((int)$resource['id'],$resource['resource_type'],max(1,self::integer($r,'page',1)));
        if(current_user_can('manage_options')){$result['issues']=$wpdb->get_results('SELECT post_id,ref_id,issue FROM '.LPM_Schema::table('usage_refs').' WHERE issue IS NOT NULL LIMIT 20',ARRAY_A);}
        return $result;
    }

    public static function page(): void {
        if (!current_user_can('edit_posts')&&!current_user_can('edit_pages')) { return; }
        try { LPM_Schema::ready(); }
        catch (Throwable $e) { echo '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>'; return; }
        echo '<div class="wrap" id="lpm-app"><div id="lpm-notice" role="status" aria-live="polite"></div><div id="lpm-root">Memuat Pricelist...</div></div>';
    }
}
