<?php
defined('ABSPATH') || exit;
final class LPM_Renderer {
    private static function link(string $text,string $url,bool $tab,string $class=''): string {
        $label=nl2br(esc_html($text));
        return $url===''?$label:'<a class="'.esc_attr($class).'" href="'.esc_url($url).'"'.($tab?' target="_blank" rel="noopener noreferrer"':'').'>'.$label.'</a>';
    }
    private static function cta(array $cta): string { return $cta['url']?self::link($cta['label'],$cta['url'],$cta['new_tab'],'lpm-cta'):''; }
    private static function pricing_cell(array $cell,bool $price): string {
        $text=nl2br(esc_html($cell['text']));
        if($price && preg_match('/^\s*(?:Rp\.?\s*)?([0-9][0-9., ]*)\s*$/u',$cell['text'],$match)){
            $amount=trim($match[1]);
            if(preg_match('/^\d+$/D',$amount)){$amount=preg_replace('/\B(?=(\d{3})+(?!\d))/','.', $amount);}
            $text='<span class="lpm-currency">Rp</span><span>'.esc_html($amount).'</span>';
        }
        return $cell['url']?'<a href="'.esc_url($cell['url']).'"'.($cell['new_tab']?' target="_blank" rel="noopener noreferrer"':'').'>'.$text.'</a>':$text;
    }
    private static function pricing(array $content): string {
        $p=$content['presentation'];$out='<div class="lpm-pricing-card"><div class="lpm-scroll" role="region" aria-label="Tabel paket harga, geser untuk melihat semua paket" tabindex="0"><table class="lpm-table lpm-pricing-table" style="min-width:'.max(760,count($content['headers'])*190).'px" data-orientation="vertical"><thead><tr>';
        foreach($content['headers'] as $c=>$header){
            $column=$p['columns'][$c];$featured=$c>0&&$p['featured_column']===$c;
            $out.='<th scope="col"'.($featured?' class="lpm-featured"':'').'><div class="lpm-package-heading"><div>'.($column['subtitle']!==''?'<small>'.esc_html($column['subtitle']).'</small>':'').'<span>'.esc_html($header).'</span></div>'.($column['badge']!==''?'<span class="lpm-package-badge">'.esc_html($column['badge']).'</span>':'').'</div></th>';
        }
        $out.='</tr></thead><tbody>';
        foreach($content['rows'] as $r=>$row){$out.='<tr>';foreach($row as $c=>$cell){$tag=$c===0?'th':'td';$out.='<'.$tag.($c===0?' scope="row"':'').($c>0&&$p['featured_column']===$c?' class="lpm-featured"':'').'>';
            if($c===0)$out.='<span class="lpm-level-dot" style="--lpm-dot:'.['#0ea5e9','#3b82f6','#3b82f6','#6366f1','#10b981','#059669','#8b5cf6','#7c3aed','#f59e0b','#d97706'][$r%10].'" aria-hidden="true"></span>';
            $out.=self::pricing_cell($cell,!isset($content['table_options'])&&$c>0).'</'.$tag.'>';}$out.='</tr>';}
        $out.='</tbody></table></div>';
        if($p['footer_note']!==''||$p['footer_title']!==''||$content['cta']['url']!==''){
            $out.='<footer class="lpm-pricing-footer"><div class="lpm-footer-message"><span class="lpm-footer-check" aria-hidden="true">&#10003;</span><div>'.($p['footer_note']!==''?'<p>'.esc_html($p['footer_note']).'</p>':'').($p['footer_title']!==''?'<strong>'.esc_html($p['footer_title']).'</strong>':'').'</div></div>';
            if($content['cta']['url']!==''){
                $wa=in_array(strtolower((string)wp_parse_url($content['cta']['url'],PHP_URL_HOST)),['wa.me','api.whatsapp.com','web.whatsapp.com','www.whatsapp.com'],true);
                $icon=$wa?'<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20.5 3.5A11.9 11.9 0 0 0 1.8 17.8L.2 23.6l5.9-1.5a11.9 11.9 0 0 0 5.7 1.4h.1A11.9 11.9 0 0 0 20.5 3.5ZM12 21.5a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.5.9.9-3.4-.2-.4A9.9 9.9 0 1 1 12 21.5Zm5.4-7.4c-.3-.2-1.8-.9-2.1-1-.3-.1-.5-.2-.7.2s-.8 1-1 1.2c-.2.2-.4.2-.7.1-.3-.2-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.5-.6.3-.5c.1-.2 0-.4 0-.6l-1-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4-.3.3-1.1 1.1-1.1 2.6s1.1 3 1.3 3.2c.1.2 2.2 3.4 5.4 4.8.8.3 1.4.5 1.8.6.8.2 1.5.2 2.1.1.6-.1 1.8-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.2-.3-.3-.6-.5Z"/></svg>':'<span aria-hidden="true">&#8599;</span>';
                $out.='<a class="lpm-footer-cta" href="'.esc_url($content['cta']['url']).'"'.($content['cta']['new_tab']?' target="_blank" rel="noopener noreferrer"':'').'>'.$icon.esc_html($content['cta']['label']).'</a>';
            }
            $out.='</footer>';
        }
        return $out.'</div>';
    }
    public static function render(string $type,array $content): string {
        if(LPM_Content::schema($content)===1){return LPM_Legacy_Renderer::render($type,$content);}
        $content=LPM_Content::validate($type,$content);$style=$content['style']['inherit']?LPM_Styling::get():$content['style']['values'];$style=LPM_Styling::validate($style);
        if($type==='price_table'&&isset($content['table_options'])){
            $options=$content['table_options'];$mobile=$options['mobile_orientation'];
            if($mobile!=='inherit'&&$mobile!==$content['orientation']){
                $desktop=$content;$desktop['table_options']['mobile_orientation']='inherit';
                $phone=$desktop;$phone['orientation']=$mobile;
                return '<div class="lpm-responsive"><div class="lpm-desktop-view">'.self::render($type,$desktop).'</div><div class="lpm-mobile-view">'.self::render($type,$phone).'</div></div>';
            }
            foreach($content['rows'] as &$row)foreach($options['price_columns'] as $column)$row[$column]['text']=LPM_Money::format($row[$column]['text'],$options);
            unset($row);
        }
        $vars=[];foreach($style as $key=>$value){if($key==='zebra'){continue;}$vars[]='--lpm-'.str_replace('_','-',$key).':'.$value.(is_int($value)?'px':'');}
        $out='<section class="lpm-resource'.($style['zebra']?' lpm-zebra':'').(isset($content['table_options'])?($content['table_options']['freeze_first_column']?' lpm-freeze-first':' lpm-no-freeze'):'').'" style="'.esc_attr(implode(';',$vars)).'">';
        if($content['show_title']&&$content['title']!==''){$out.='<h2>'.esc_html($content['title']).'</h2>';}
        if(($content['show_description']??true)&&$content['description']!==''){$out.='<p>'.nl2br(esc_html($content['description'])).'</p>';}
        if($type==='price_table'&&($content['presentation']['theme']??'classic')==='pricing'&&$content['orientation']==='vertical'){
            return $out.self::pricing($content).'</section>';
        }
        if($type==='price_table'){
            $matrix=array_merge([array_map([LPM_Content::class,'cell'],$content['headers'])],$content['rows']);
            if($content['orientation']==='horizontal'){$transposed=[];foreach(array_keys($content['headers']) as $col){$transposed[]=array_column($matrix,$col);}$matrix=$transposed;}
            $out.='<div class="lpm-scroll" role="region" aria-label="Tabel harga" tabindex="0"><table class="lpm-table" data-orientation="'.esc_attr($content['orientation']).'">';
            foreach($matrix as $r=>$row){$out.=$r===0?'<thead><tr>':($r===1?'<tbody><tr>':'<tr>');foreach($row as $c=>$cell){$tag=$r===0||$c===0?'th':'td';$out.='<'.$tag.($tag==='th'?' scope="'.($r===0?'col':'row').'"':'').'>'.self::link($cell['text'],$cell['url'],$cell['new_tab']).'</'.$tag.'>';}$out.='</tr>'.($r===0?'</thead>':'');}
            $out.=(count($matrix)>1?'</tbody>':'').'</table></div>';
        }else{
            $carousel=$content['mode']==='carousel';
            if($carousel){wp_enqueue_script('lpm-carousel',plugins_url('assets/carousel.js',LPM_FILE),[],LPM_VERSION,true);}
            $out.='<div class="lpm-gallery"'.($carousel?' data-lpm-carousel data-autoplay="'.($content['autoplay']?'1':'0').'" data-interval="'.(int)$content['interval'].'" role="region" aria-roledescription="carousel" aria-label="Katalog harga" tabindex="0"':'').'>';
            foreach($content['items'] as $i=>$item){
                $image=LPM_Content::media_exists($item['attachment_id'])?'<img src="'.esc_url($item['image_url']).'" alt="'.esc_attr($item['alt']).'" loading="lazy" decoding="async">':'<div class="lpm-placeholder" role="img" aria-label="Gambar tidak tersedia">Gambar tidak tersedia</div>';
                if($item['link']){$image='<a href="'.esc_url($item['link']).'"'.($item['new_tab']?' target="_blank" rel="noopener noreferrer"':'').'>'.$image.'</a>';}
                $out.='<article class="lpm-slide"'.($carousel?' role="group" aria-label="'.($i+1).' dari '.count($content['items']).'"':'').'><figure>'.$image.($item['caption']?'<figcaption>'.esc_html($item['caption']).'</figcaption>':'').'</figure>';
                if($item['title']){$out.='<h3>'.esc_html($item['title']).'</h3>';}
                if($item['price']){$out.='<p class="lpm-price">'.esc_html($item['price']).'</p>';}
                if($item['description']){$out.='<p>'.nl2br(esc_html($item['description'])).'</p>';}
                $out.=self::cta($item['cta']).'</article>';
            }
            if($carousel){$out.='<div class="lpm-controls" hidden><button type="button" data-prev aria-label="Gambar sebelumnya">&#8592;</button><span data-position role="status" aria-live="polite"></span><button type="button" data-next aria-label="Gambar berikutnya">&#8594;</button>'.($content['autoplay']?'<button type="button" data-pause>Pause</button>':'').'</div>';}
            $out.='</div>';
        }
        return $out.self::cta($content['cta']).'</section>';
    }
    public static function shortcode(string $type,array $atts): string {
        try{$id=isset($atts['id'])&&ctype_digit((string)$atts['id'])?(int)$atts['id']:0;if(!$id){return '';}$r=LPM_Service::resource($id);if($r['resource_type']!==$type||!$r['production_version_id']){return '';}
            $content=LPM_Service::snapshot(LPM_Service::version($r,(int)$r['production_version_id']));
            if(array_key_exists('show_title',$atts)||array_key_exists('show_description',$atts)){
                $content=LPM_Content::upgrade($type,$content);
                foreach(['show_title','show_description'] as $key){
                    if(!array_key_exists($key,$atts))continue;
                    $value=strtolower((string)$atts[$key]);
                    if(in_array($value,['0','false','no'],true))$content[$key]=false;
                    elseif(in_array($value,['1','true','yes'],true))$content[$key]=true;
                }
            }
            return self::render($type,$content);
        }catch(Throwable $e){error_log('LPM render unavailable resource='.(int)($id??0).' category='.get_class($e));return '';}
    }
}
