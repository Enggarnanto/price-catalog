<?php
defined('ABSPATH') || exit;
final class LPM_Usage {
    public static function boot(): void {
        add_action('wp_after_insert_post',[self::class,'changed'],10,1);
        add_action('deleted_post',[self::class,'changed'],10,1);
        add_action('lpm_usage_rebuild',[self::class,'step']);
        if (!get_option('lpm_usage_index') && !wp_next_scheduled('lpm_usage_rebuild')) { wp_schedule_single_event(time()+10,'lpm_usage_rebuild'); }
    }
    public static function scan(string $content,array $seen=[],int $depth=0): array {
        $found=[];$refs=[];
        if (preg_match_all('/'.get_shortcode_regex(LPM_Shortcode::tags()).'/s',$content,$matches,PREG_SET_ORDER)) {
            foreach($matches as $m) {
                if ($m[1]==='[' && $m[6]===']') { continue; }
                $atts=shortcode_parse_atts($m[3]);$id=is_array($atts)?($atts['id']??''):'';
                if (ctype_digit((string)$id)&&(int)$id>0) {
                    if(in_array($m[2],['latis_price_table','latis_price_catalog'],true)){$type=$m[2]==='latis_price_table'?'price_table':'price_catalog';}
                    else{try{$type=LPM_Service::resource((int)$id)['resource_type'];}catch(Throwable $e){continue;}}
                    $found[$type.':'.(int)$id]=[$type,(int)$id];
                }
            }
        }
        $walk=static function(array $blocks) use (&$walk,&$found,&$refs,$seen,$depth) {
            foreach($blocks as $block) {
                if ($block['blockName']==='core/block' && isset($block['attrs']['ref'])) {
                    $ref=(int)$block['attrs']['ref'];$post=get_post($ref);$issue=null;
                    if(in_array($ref,$seen,true)){$issue='Siklus synced block';}
                    elseif($depth>=10){$issue='Batas kedalaman synced block';}
                    elseif(!$post || $post->post_type!=='wp_block'||in_array($post->post_status,['trash','auto-draft'],true)){$issue='Referensi synced block tidak tersedia';}
                    else{$nested=self::scan($post->post_content,array_merge($seen,[$ref]),$depth+1);$found+=$nested['resources'];$refs+=$nested['refs'];}
                    $refs[$ref]=$issue??($refs[$ref]??null);
                }
                if (!empty($block['innerBlocks'])) { $walk($block['innerBlocks']); }
            }
        };
        $walk(parse_blocks($content));return ['resources'=>$found,'refs'=>$refs];
    }
    public static function index(int $id): void {
        global $wpdb;$u=LPM_Schema::table('usage');$r=LPM_Schema::table('usage_refs');$post=get_post($id);
        if($wpdb->delete($u,['post_id'=>$id])===false||$wpdb->delete($r,['post_id'=>$id])===false){throw new RuntimeException('Indeks penggunaan gagal diperbarui.');}
        if(!$post||!in_array($post->post_type,['post','page','wp_block'],true)||in_array($post->post_status,['trash','auto-draft','inherit'],true)){return;}
        $scan=self::scan($post->post_content,[$id]);
        foreach($scan['resources'] as [$type,$resource]){if($wpdb->replace($u,['post_id'=>$id,'resource_type'=>$type,'resource_id'=>$resource])===false){throw new RuntimeException('Indeks penggunaan gagal.');}}
        foreach($scan['refs'] as $ref=>$issue){if($wpdb->replace($r,['post_id'=>$id,'ref_id'=>$ref,'issue'=>$issue])===false){throw new RuntimeException('Indeks referensi gagal.');}}
    }
    public static function changed(int $id): void {
        global $wpdb;
        if(wp_is_post_revision($id)||wp_is_post_autosave($id)){return;}
        try {
            $dependents=$wpdb->get_col($wpdb->prepare('SELECT post_id FROM '.LPM_Schema::table('usage_refs').' WHERE ref_id=%d',$id));
            self::index($id);foreach($dependents as $owner){self::index((int)$owner);}
        }catch(Throwable $e){$status=get_option('lpm_usage_index',[]);$status['complete']=false;$status['error']='Indeks perlu dibangun ulang.';update_option('lpm_usage_index',$status,false);}
    }
    public static function step(bool $reset=false): array {
        global $wpdb;
        $lock='lpm_usage_'.substr(hash('sha256',DB_NAME.$wpdb->prefix),0,30);
        if((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!=='1'){throw new RuntimeException('Rebuild lain sedang berjalan.');}
        try{
            $s=get_option('lpm_usage_index',[]);
            if($reset||!isset($s['max'])){$s=['cursor'=>0,'max'=>(int)$wpdb->get_var("SELECT MAX(ID) FROM $wpdb->posts"),'complete'=>false];}
            $ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE ID>%d AND ID<=%d AND post_type IN ('post','page','wp_block') ORDER BY ID LIMIT 30",$s['cursor'],$s['max']));
            foreach($ids as $id){self::index((int)$id);$s['cursor']=(int)$id;}
            $s['complete']=count($ids)<30;$s['updated_at']=gmdate('Y-m-d H:i:s');unset($s['error']);update_option('lpm_usage_index',$s,false);
            if(!$s['complete']&&!wp_next_scheduled('lpm_usage_rebuild')){wp_schedule_single_event(time()+30,'lpm_usage_rebuild');}
            return $s;
        }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    public static function posts(int $resource,string $type,int $page=1): array {
        global $wpdb;$offset=max(0,$page-1)*20;
        $sql=$wpdb->prepare('SELECT p.ID,p.post_title,p.post_type,p.post_status,p.post_author,p.post_modified FROM '.$wpdb->posts.' p JOIN '.LPM_Schema::table('usage')." u ON p.ID=u.post_id WHERE u.resource_id=%d AND u.resource_type=%s AND p.post_type IN ('post','page') AND p.post_status NOT IN ('trash','auto-draft') ORDER BY p.post_modified_gmt DESC",$resource,$type);
        $allowed=[];foreach($wpdb->get_results($sql) as $post){if(current_user_can('read_post',(int)$post->ID)){$allowed[]=['id'=>(int)$post->ID,'title'=>$post->post_title?:'(Tanpa judul)','type'=>$post->post_type,'status'=>$post->post_status,'author'=>get_the_author_meta('display_name',$post->post_author),'updated'=>$post->post_modified,'edit'=>current_user_can('edit_post',$post->ID)?get_edit_post_link($post->ID,'raw'):null,'view'=>get_permalink($post->ID)];}}
        return ['items'=>array_slice($allowed,$offset,20),'total'=>count($allowed),'index'=>get_option('lpm_usage_index',['complete'=>false])];
    }
}
