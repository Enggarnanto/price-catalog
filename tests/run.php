<?php
require __DIR__ . '/bootstrap.php';
$checks = 0;
function check(bool $value, string $message): void {
    global $checks;
    if (!$value) { throw new RuntimeException('FAIL: ' . $message); }
    $checks++; echo "PASS $message\n";
}
function rejects(callable $action, string $class, string $message): void {
    try { $action(); } catch (Throwable $e) { check($e instanceof $class, $message . ' [' . get_class($e) . ']'); return; }
    check(false, $message);
}
function content(string $title = 'Paket belajar'): array {
    $data = LPM_Legacy_Content::initial('price_table');
    $data['title'] = $title;
    $data['items'] = [['title' => 'Paket A', 'price' => 'Mulai Rp150.000', 'description' => 'Harga per sesi', 'cta' => ['label' => 'Daftar', 'url' => 'https://example.com/daftar', 'new_tab' => true]]];
    return $data;
}
function op(): string { return 'test_' . bin2hex(random_bytes(16)); }
function render(int $id): string { return do_shortcode('[latis_price_table id="' . $id . '"]'); }
function save_current(int $id, array $data): array {
    $s = LPM_Service::editor($id);
    return LPM_Service::save($id, $data, (int) $s['resource']['draft_revision'], $s['base']);
}
function publish_current(int $id): array {
    $r = LPM_Service::resource($id);
    return LPM_Service::publish($id, (int) $r['draft_revision'], (int) $r['production_version_id'], 'Test publication', op());
}
function race(array $jobs): array {
    $processes = []; $start = (string) (microtime(true) + 2);
    foreach ($jobs as $job) {
        $command = [PHP_BINARY, __DIR__ . '/worker.php', $job['mode'], (string) $job['id'], (string) $job['revision'], (string) $job['production'], op(), $start, (string) ($job['source'] ?? 0)];
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Unable to start concurrent worker.'); }
        fclose($pipes[0]); $processes[] = [$process, $pipes];
    }
    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0) { throw new RuntimeException('Worker failed: ' . $out . $err); }
        $results[] = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }
    return $results;
}

LPM_Schema::install();
check(get_option('lpm_db_version') === LPM_Schema::VERSION, 'Schema installed and InnoDB verified');
$id = LPM_Service::create('price_table', 'Integration ' . op());
check(render($id) === '', 'Unpublished resource renders empty');
$draft = content(); save_current($id, $draft);
check(render($id) === '', 'Saving initial draft does not expose it publicly');
$before = LPM_Service::resource($id);
$operation = op();
$first = LPM_Service::publish($id, (int) $before['draft_revision'], 0, 'Initial publication', $operation);
check($first['version_number'] === 1 && str_contains(render($id), 'Rp150.000'), 'V1 published through stable shortcode');
$v1 = LPM_Service::version(LPM_Service::resource($id), $first['version_id']);
$replay = LPM_Service::publish($id, (int) $before['draft_revision'], 0, 'Initial publication', $operation);
check($replay['replayed'] && $replay['version_id'] === $first['version_id'] && count(LPM_Service::history($id)) === 1, 'Lost-response retry creates exactly one version');
rejects(fn() => LPM_Service::publish($id, (int) $before['draft_revision'], 0, 'Different payload', $operation), LPM_Conflict::class, 'Operation key cannot be reused with different payload');
rejects(fn() => LPM_Service::save($id, $draft, (int) $before['draft_revision'], 0), LPM_Conflict::class, 'Old editor cannot save after publish clears draft');
$draft['items'][0]['price'] = 'Rp200.000'; save_current($id, $draft);
check(str_contains(render($id), 'Rp150.000') && !str_contains(render($id), 'Rp200.000'), 'Saved changes leave published price unchanged');
$row = LPM_Service::resource($id);
save_current($id, $draft);
rejects(fn() => LPM_Service::save($id, $draft, (int) $row['draft_revision'], $first['version_id']), LPM_Conflict::class, 'Stale draft revision rejected');

foreach (['after_snapshot', 'after_pointer', 'before_commit'] as $stage) {
    $r = LPM_Service::resource($id); $versions = LPM_Service::history($id); $events = $wpdb->get_results('SELECT * FROM ' . LPM_Schema::table('cache_events'), ARRAY_A);
    LPM_Service::$test_fault = static function ($current) use ($stage) { if ($current === $stage) { throw new RuntimeException('Injected transaction failure'); } };
    rejects(fn() => publish_current($id), RuntimeException::class, 'Failure injection ' . $stage);
    LPM_Service::$test_fault = null;
    check(LPM_Service::resource($id) === $r && LPM_Service::history($id) === $versions && $events === $wpdb->get_results('SELECT * FROM ' . LPM_Schema::table('cache_events'), ARRAY_A), 'Full rollback preserves resource, history and outbox at ' . $stage);
}
$second = publish_current($id);
check($second['version_number'] === 2 && str_contains(render($id), 'Rp200.000'), 'V2 activates after failed attempts');
check(LPM_Service::version(LPM_Service::resource($id), $first['version_id'])['snapshot_data'] === $v1['snapshot_data'], 'V1 snapshot remains immutable');
save_current($id,content('Connection interruption'));
$connection_before = LPM_Service::resource($id); $connection_history = LPM_Service::history($id);
LPM_Service::$test_fault = static function ($stage) {
    global $wpdb;
    if ($stage === 'after_snapshot') {
        $killer = new mysqli('127.0.0.1','root','','lpm_integration',13317);
        $killer->kill(mysqli_thread_id(LPM_Database::connection())); $killer->close();
    }
};
rejects(fn() => publish_current($id), RuntimeException::class, 'Lost database connection aborts publication');
LPM_Service::$test_fault = null;
$wpdb->check_connection(false);
check(LPM_Service::resource($id)===$connection_before && LPM_Service::history($id)===$connection_history, 'Lost connection cannot replay writes outside the transaction');
save_current($id, content('Pekerjaan belum selesai'));
$before_restore = LPM_Service::resource($id);
$restored = LPM_Service::publish($id, (int) $before_restore['draft_revision'], $second['version_id'], 'Restore first version', op(), $first['version_id']);
$after_restore = LPM_Service::resource($id);
check($restored['version_number'] === 3 && str_contains(render($id), 'Rp150.000'), 'Restore V1 creates V3 and activates original content');
check($before_restore['draft_data'] === $after_restore['draft_data'] && $before_restore['draft_revision'] === $after_restore['draft_revision'] && LPM_Service::editor($id)['stale'], 'Restore preserves existing draft and makes it stale');
rejects(fn() => publish_current($id), LPM_Conflict::class, 'Stale draft cannot overwrite restored production');
rejects(fn() => LPM_Service::save($id, $draft, (int) $after_restore['draft_revision'], $restored['version_id']), LPM_Conflict::class, 'Normal save cannot silently change draft base');
rejects(fn() => LPM_Service::save($id, $draft, (int) $after_restore['draft_revision'], $second['version_id'], true, $second['version_id']), LPM_Conflict::class, 'Reconciliation against old production rejected');
LPM_Service::save($id, $draft, (int) $after_restore['draft_revision'], $restored['version_id'], true, $restored['version_id']);
check(!LPM_Service::editor($id)['stale'], 'Explicit reconciliation updates base');
$fourth = publish_current($id);
check($fourth['version_number'] === 4 && count(LPM_Service::history($id)) === 4, 'Reconciled draft publishes V4 with full history');
rejects(fn() => LPM_Service::publish($id, (int) LPM_Service::resource($id)['draft_revision'], $fourth['version_id'], 'No-op restore', op(), $fourth['version_id']), InvalidArgumentException::class, 'Restoring active production is rejected');
$other = LPM_Service::create('price_table', 'Other');
rejects(fn() => LPM_Service::version(LPM_Service::resource($other), $first['version_id']), InvalidArgumentException::class, 'Cross-resource version reference rejected');

$fail_cache = static function () { throw new RuntimeException('Cache provider unavailable'); };
add_action('lpm_invalidate_resource_cache', $fail_cache);
save_current($id, content('Cache test')); $cached_publish = publish_current($id);
check($cached_publish['cache_pending'] === 1 && str_contains(render($id), 'Cache test'), 'Cache failure leaves successful production and durable retry event');
remove_action('lpm_invalidate_resource_cache', $fail_cache);
LPM_Cache::process(true);
check(LPM_Cache::pending($id) === 0, 'Cache retry completes pending outbox event');
LPM_Cache::process(true);
check(LPM_Cache::pending($id) === 0, 'Completed cache events are not processed again');

check(LPM_Content::json(['b'=>2,'a'=>1]) === LPM_Content::json(['a'=>1,'b'=>2]), 'Canonical JSON ignores object key order');
check(LPM_Content::json([1,2]) !== LPM_Content::json([2,1]), 'Canonical JSON preserves item ordering');
check(count(LPM_Content::diff(['items'=>['a','b']], ['items'=>['b','a']])) === 2, 'Semantic compare detects reordering');
$bad = content(); $bad['items'][0]['cta']['url'] = 'javascript:alert(1)';
rejects(fn() => save_current($id, $bad), InvalidArgumentException::class, 'Executable CTA URL rejected');
$bad = content(); $bad['title'] = '<script>alert(1)</script>';
rejects(fn() => save_current($id, $bad), InvalidArgumentException::class, 'HTML payload rejected');
$bad = content(); $bad['style']['accent'] = 'red;background:url(javascript:1)';
rejects(fn() => save_current($id, $bad), InvalidArgumentException::class, 'Arbitrary CSS rejected');
rejects(fn() => LPM_Content::decode(str_repeat('x', LPM_Content::MAX_BYTES+1)), InvalidArgumentException::class, 'Oversized payload rejected');
rejects(fn() => LPM_Content::validate('price_table', content(), false, 99), InvalidArgumentException::class, 'Unsupported schema fails closed');
$safe = content('Quotes " & apostrophe'); $safe['items'][0]['cta']['url'] = 'https://example.com/?a=1&b=2'; save_current($id, $safe); publish_current($id);
check(str_contains(render($id), '&amp;') && str_contains(render($id), 'noopener noreferrer'), 'Renderer escapes output and protects new-tab links');
check(do_shortcode('[latis_price_table id="'.$id.'" version="'.$first['version_id'].'"]') === render($id), 'Public shortcode cannot select archived version');

// Media snapshots preserve order, URLs, alt text and CTA; missing media blocks future publication.
require_once ABSPATH . 'wp-admin/includes/image.php';
$upload = wp_upload_bits('lpm-test.png', null, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7ioAAAAASUVORK5CYII='));
if ($upload['error']) { throw new RuntimeException($upload['error']); }
$attachment = wp_insert_attachment(['post_title'=>'Test image','post_mime_type'=>'image/png','post_status'=>'inherit'], $upload['file']);
$catalog_id = LPM_Service::create('price_catalog', 'Catalog');
$catalog = content('Catalog media');
$catalog['items'][0] += ['attachment_id'=>$attachment,'image_url'=>$upload['url'],'alt'=>'Alt gambar','caption'=>'Caption'];
$catalog['items'][] = array_merge($catalog['items'][0], ['title'=>'Item kedua','caption'=>'Kedua']);
save_current($catalog_id, $catalog); $catalog_v1 = publish_current($catalog_id);
$snap = LPM_Service::snapshot(LPM_Service::version(LPM_Service::resource($catalog_id), $catalog_v1['version_id']));
check(LPM_Content::json($snap['items']) === LPM_Content::json($catalog['items']), 'Catalog snapshot preserves ordered images, URLs, CTA and captions');
check(str_contains(do_shortcode('[latis_price_catalog id="'.$catalog_id.'"]'), 'lpm-card'), 'Catalog shortcode renders published cards');
wp_delete_attachment($attachment, true);
check(str_contains(do_shortcode('[latis_price_catalog id="'.$catalog_id.'"]'), 'Gambar tidak tersedia'), 'Deleted media renders safe placeholder');
save_current($catalog_id, $catalog);
rejects(fn() => publish_current($catalog_id), InvalidArgumentException::class, 'Missing mandatory attachment blocks publish');

// REST capability/nonce and review-gating checks.
do_action('rest_api_init');
$request = static function (string $method, string $path, array $body = [], bool $nonce = true) {
    $r = new WP_REST_Request($method, '/lpm/v1' . $path);
    if ($nonce) { $r->set_header('X-WP-Nonce', wp_create_nonce('wp_rest')); }
    foreach ($body as $key => $value) { $r->set_param($key, $value); }
    return rest_do_request($r);
};
check($request('GET','/resources/'.$id,[],false)->get_status() === 403, 'REST private read requires nonce');
wp_set_current_user(0);
check($request('GET','/resources/'.$id)->get_status() === 403, 'Unauthenticated user cannot read draft');
rejects(fn() => LPM_Service::save($id,content(),0,0), RuntimeException::class, 'Service blocks unauthorized mutation');
$subscriber = wp_create_user('lpm-subscriber-'.op(),bin2hex(random_bytes(15)));
(new WP_User($subscriber))->set_role('subscriber'); wp_set_current_user($subscriber);
check($request('GET','/resources/'.$id.'/review')->get_status() === 403, 'Subscriber cannot preview private snapshot');
wp_set_current_user(1);
$review = $request('GET','/resources/'.$id.'/review')->get_data();
check(isset($review['html'],$review['changes'],$review['review_token']), 'Authorized preview returns renderer, semantic diff and review proof');
$current = LPM_Service::resource($id);
check($request('POST','/resources/'.$id.'/publish',['revision'=>(int)$current['draft_revision'],'production'=>(int)$current['production_version_id'],'summary'=>'No preview','operation_key'=>op()])->get_status() === 409, 'Publish endpoint requires matching review proof');
$response = apply_filters('rest_post_dispatch', new WP_REST_Response([]), rest_get_server(), new WP_REST_Request('GET','/lpm/v1/resources/'.$id));
check(str_contains($response->get_headers()['Cache-Control'], 'no-store') && str_contains($response->get_headers()['X-Robots-Tag'],'noindex'), 'Private REST responses carry no-store and noindex headers');
save_current($id,content('REST publish'));
$review = $request('GET','/resources/'.$id.'/review')->get_data();
$rest_payload = ['revision'=>$review['revision'],'production'=>$review['production'],'review_token'=>$review['review_token'],'source'=>0,'summary'=>'Reviewed REST publish','operation_key'=>op()];
$rest_published = $request('POST','/resources/'.$id.'/publish',$rest_payload);
check($rest_published->get_status()===200 && str_contains(render($id),'REST publish'), 'Reviewed REST request publishes successfully');
check($request('POST','/resources/'.$id.'/publish',$rest_payload)->get_data()['replayed']===true, 'Reviewed REST retry replays original result');
$metadata = LPM_Service::history($id);
check(!isset($metadata[0]['snapshot_data']) && count(LPM_Service::history($id,1,2))===2, 'History pagination excludes large snapshot payloads');
$restore_review = $request('GET','/resources/'.$id.'/review',['source'=>$first['version_id']])->get_data();
save_current($id,content('Draft to retain'));
check($request('POST','/resources/'.$id.'/publish',['revision'=>$restore_review['revision'],'production'=>$restore_review['production'],'source'=>$first['version_id'],'summary'=>'Outdated restore review','operation_key'=>op(),'review_token'=>$restore_review['review_token']])->get_status()===409, 'Restore review becomes invalid if draft changes');

// Separate PHP processes use independent wpdb connections and compete on the same row.
$race_id = LPM_Service::create('price_table', 'Concurrency'); save_current($race_id,content());
$r = LPM_Service::resource($race_id); $job = ['mode'=>'save','id'=>$race_id,'revision'=>(int)$r['draft_revision'],'production'=>0];
$results = race([$job,$job]);
check(count(array_filter($results,fn($r)=>$r['ok']))===1 && count(array_filter($results,fn($r)=>isset($r['conflict'])))===1, 'Two independent connections: one save succeeds, one conflicts');
$r = LPM_Service::resource($race_id); $job = ['mode'=>'publish','id'=>$race_id,'revision'=>(int)$r['draft_revision'],'production'=>0];
$results = race([$job,$job]);
check(count(array_filter($results,fn($r)=>$r['ok']))===1 && count(LPM_Service::history($race_id))===1, 'Two independent connections: one publish succeeds, no silent overwrite');
$race_v1 = (int) LPM_Service::resource($race_id)['production_version_id']; save_current($race_id,content('Race V2')); publish_current($race_id); save_current($race_id,content('Race V3'));
$r = LPM_Service::resource($race_id); $job = ['mode'=>'publish','id'=>$race_id,'revision'=>(int)$r['draft_revision'],'production'=>(int)$r['production_version_id']];
$results = race([$job,array_merge($job,['mode'=>'restore','source'=>$race_v1])]);
check(count(array_filter($results,fn($r)=>$r['ok']))===1 && count(LPM_Service::history($race_id))===3, 'Concurrent publish versus restore cannot silently overwrite');

$deactivation_before = LPM_Service::resource($id);
$history_before = LPM_Service::history($id);
do_action('deactivate_' . plugin_basename(LPM_FILE));
check(LPM_Service::resource($id)===$deactivation_before && LPM_Service::history($id)===$history_before, 'Deactivation preserves draft, production and immutable history');

// Readiness must fail closed if an engine changes after installation.
$table = LPM_Schema::table('cache_events'); $wpdb->query("ALTER TABLE $table ENGINE=MyISAM");
rejects(fn() => LPM_Service::create('price_table','Unsafe engine'), RuntimeException::class, 'Non-InnoDB table blocks mutations');
$wpdb->query("ALTER TABLE $table ENGINE=InnoDB"); LPM_Schema::ready();
$preserved = LPM_Service::version(LPM_Service::resource($id), $first['version_id'])['snapshot_data'];
LPM_Schema::install();
check(LPM_Service::version(LPM_Service::resource($id), $first['version_id'])['snapshot_data'] === $preserved, 'Schema reinstall preserves immutable history');
$r = LPM_Service::resource($other); $wpdb->update(LPM_Schema::table('resources'),['production_version_id'=>$first['version_id']],['id'=>$other]);
check(render($other)==='', 'Corrupt cross-resource production pointer fails closed');
echo "\nSUCCESS: $checks checks passed on WordPress + MySQL/InnoDB.\n";
