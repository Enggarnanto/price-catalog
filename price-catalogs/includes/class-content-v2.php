<?php
defined('ABSPATH') || exit;

final class LPM_Content {
    public const SCHEMA = 5;
    public const MAX_BYTES = 1048576;
    public static function json($data): string { return LPM_Legacy_Content::json($data); }
    public static function decode(string $json): array { return LPM_Legacy_Content::decode($json); }
    public static function diff($a, $b, string $path = '$'): array { return LPM_Legacy_Content::diff($a, $b, $path); }
    public static function media_exists(int $id): bool { return LPM_Legacy_Content::media_exists($id); }
    public static function schema(array $data): int { return isset($data['show_title']) ? (array_key_exists('table_options',$data)?5:(array_key_exists('show_description',$data)?4:(array_key_exists('presentation',$data)?3:2))) : 1; }
    public static function cta(): array { return ['label' => '', 'url' => '', 'new_tab' => false]; }
    public static function cell(string $text = '', string $url = '', bool $new_tab = false): array { return compact('text', 'url', 'new_tab'); }
    public static function presentation(array $headers, string $theme='classic'): array {
        return ['theme'=>$theme,'featured_column'=>0,'columns'=>array_map(static fn($h)=>['subtitle'=>'','badge'=>''],$headers),
            'footer_note'=>'Kebutuhan spesifik & kurikulum internasional?',
            'footer_title'=>'Konsultasikan jadwal & diskon paket belajar keluarga'];
    }
    public static function reference_headers(array $headers): bool {
        return array_map(static fn($h)=>strtolower(trim($h)),$headers)===['jenjang','pintar','cerdas','genius'];
    }
    public static function initial(string $type): array {
        $common = ['title' => '', 'show_title' => true, 'description' => '', 'style' => ['inherit' => true, 'values' => LPM_Styling::defaults()], 'cta' => self::cta()];
        return $type === 'price_table' ? $common + ['program' => 'reguler', 'orientation' => 'vertical', 'headers' => ['Paket', 'Harga', 'Keterangan'],
            'rows' => [[self::cell(), self::cell(), self::cell()]]] : $common + ['mode' => 'single', 'autoplay' => false, 'interval' => 5, 'items' => []];
    }
    public static function text($v, int $max): string {
        if (!is_string($v) || mb_strlen($v) > $max || wp_check_invalid_utf8($v) !== $v || preg_match('/[<>]/u', $v)) { throw new InvalidArgumentException('Teks terlalu panjang atau mengandung markup HTML.'); }
        return $v;
    }
    public static function url($v): string {
        self::text($v, 2048);
        if ($v !== '' && (!filter_var($v, FILTER_VALIDATE_URL) || !in_array(strtolower((string)wp_parse_url($v, PHP_URL_SCHEME)), ['http', 'https'], true))) { throw new InvalidArgumentException('URL harus HTTP/HTTPS absolut.'); }
        return $v;
    }
    private static function keys($data, array $keys): void {
        if (!is_array($data)) { throw new InvalidArgumentException('Struktur data tidak valid.'); }
        $actual = array_keys($data); sort($actual); sort($keys);
        if ($actual !== $keys) { throw new InvalidArgumentException('Field tidak sesuai schema v2.'); }
    }
    private static function boolean($value): void { if (!is_bool($value)) { throw new InvalidArgumentException('Nilai toggle tidak valid.'); } }
    private static function validate_cta($v): void {
        self::keys($v, ['label', 'url', 'new_tab']); self::text($v['label'], 100); self::url($v['url']); self::boolean($v['new_tab']);
        if (($v['label'] === '') !== ($v['url'] === '')) { throw new InvalidArgumentException('Label dan URL CTA harus diisi bersama.'); }
    }
    public static function validate(string $type, $data, bool $publishing = false, int $schema = 0): array {
        if (!is_array($data) || !in_array($type, ['price_table','price_catalog'], true)) { throw new InvalidArgumentException('Konten tidak valid.'); }
        $schema = $schema ?: self::schema($data);
        if ($schema === 1) { return LPM_Legacy_Content::validate($type, $data, $publishing, 1); }
        if (!in_array($schema,[2,3,4,5],true) || self::schema($data) !== $schema) { throw new InvalidArgumentException('Schema snapshot tidak didukung.'); }
        if (strlen(self::json($data)) > self::MAX_BYTES) { throw new InvalidArgumentException('Konten maksimal 1 MiB.'); }
        $common = ['title','show_title','description','style','cta'];
        if (array_key_exists('show_description',$data)) { $common[]='show_description'; self::boolean($data['show_description']); }
        if ($schema===5) { if($type!=='price_table')throw new InvalidArgumentException('Pengaturan tabel hanya untuk Tabel Price.');$common[]='table_options'; }
        if ($type==='price_table' && array_key_exists('presentation',$data)) { $common[]='presentation'; }
        self::keys($data, array_merge($common, $type === 'price_table' ? ['program','orientation','headers','rows'] : ['mode','autoplay','interval','items']));
        self::text($data['title'], 200); self::text($data['description'], 4000); self::boolean($data['show_title']); self::validate_cta($data['cta']);
        self::keys($data['style'], ['inherit','values']); self::boolean($data['style']['inherit']); LPM_Styling::validate($data['style']['values']);
        if ($type === 'price_table') {
            if (!in_array($data['program'], ['','reguler','eksklusif'], true) || !in_array($data['orientation'], ['vertical','horizontal'], true)) { throw new InvalidArgumentException('Program atau orientasi tidak valid.'); }
            if (!is_array($data['headers']) || !array_is_list($data['headers']) || count($data['headers']) < 1 || count($data['headers']) > 20) { throw new InvalidArgumentException('Tabel membutuhkan 1-20 kolom.'); }
            foreach ($data['headers'] as $header) { self::text($header, 200); }
            if($schema===5)LPM_Money::validate($data['table_options'],count($data['headers']));
            if(array_key_exists('presentation',$data)){
                $p=$data['presentation'];self::keys($p,['theme','featured_column','columns','footer_note','footer_title']);
                if(!in_array($p['theme'],['classic','pricing'],true)||!is_int($p['featured_column'])||$p['featured_column']<0||$p['featured_column']>=count($data['headers'])){throw new InvalidArgumentException('Gaya atau kolom unggulan tidak valid.');}
                self::text($p['footer_note'],300);self::text($p['footer_title'],500);
                if(!is_array($p['columns'])||!array_is_list($p['columns'])||count($p['columns'])!==count($data['headers'])){throw new InvalidArgumentException('Pengaturan paket harus sesuai jumlah kolom.');}
                foreach($p['columns'] as $column){self::keys($column,['subtitle','badge']);self::text($column['subtitle'],150);self::text($column['badge'],50);}
            }
            if (!is_array($data['rows']) || !array_is_list($data['rows']) || count($data['rows']) > 200) { throw new InvalidArgumentException('Maksimal 200 baris.'); }
            foreach ($data['rows'] as $row) {
                if (!is_array($row) || !array_is_list($row) || count($row) !== count($data['headers'])) { throw new InvalidArgumentException('Jumlah sel tidak sesuai jumlah kolom.'); }
                foreach ($row as $cell) { self::keys($cell,['text','url','new_tab']); self::text($cell['text'],4000); self::url($cell['url']); self::boolean($cell['new_tab']); }
            }
            if ($publishing && ($data['program'] === '' || !$data['rows'] || !array_filter($data['headers'], static fn($h) => trim($h) !== ''))) { throw new InvalidArgumentException('Pilih program, isi header dan minimal satu baris sebelum publish.'); }
        } else {
            if (!in_array($data['mode'],['single','carousel'],true) || !is_int($data['interval']) || $data['interval'] < 5 || $data['interval'] > 120) { throw new InvalidArgumentException('Mode/interval katalog tidak valid (5-120 detik).'); }
            self::boolean($data['autoplay']);
            if (!is_array($data['items']) || !array_is_list($data['items']) || count($data['items']) > 50) { throw new InvalidArgumentException('Maksimal 50 gambar katalog.'); }
            foreach ($data['items'] as $item) {
                self::keys($item,['title','price','description','attachment_id','image_url','alt','caption','link','new_tab','cta']);
                self::text($item['title'],200); self::text($item['price'],100); self::text($item['description'],2000); self::text($item['alt'],300); self::text($item['caption'],500);
                self::url($item['image_url']); self::url($item['link']); self::boolean($item['new_tab']); self::validate_cta($item['cta']);
                if (!is_int($item['attachment_id']) || $item['attachment_id'] < 0) { throw new InvalidArgumentException('Attachment tidak valid.'); }
                if ($publishing && (!self::media_exists($item['attachment_id']) || !$item['image_url'])) { throw new InvalidArgumentException('Gambar wajib tidak tersedia. Pilih ulang gambar pada draft.'); }
            }
            if ($publishing && (($data['mode']==='single' && count($data['items'])!==1) || ($data['mode']==='carousel' && count($data['items'])<2))) { throw new InvalidArgumentException('Single membutuhkan tepat 1 gambar; carousel minimal 2.'); }
        }
        return $data;
    }
    public static function upgrade(string $type, array $data): array {
        if (self::schema($data) >= 2) { return self::validate($type,$data); }
        $data = LPM_Legacy_Content::validate($type,$data);
        $out = self::initial($type);
        foreach (['title','description','cta'] as $field) { $out[$field] = $data[$field]; }
        $out['style']['inherit'] = false; $out['style']['values']['accent'] = $data['style']['accent'];
        if ($type === 'price_table') {
            $out['program'] = ''; $out['headers'] = ['Layanan / Produk','Harga','Keterangan','Tindakan'];
            $out['rows'] = array_map(static fn($i) => [self::cell($i['title']),self::cell($i['price']),self::cell($i['description']),self::cell($i['cta']['label'],$i['cta']['url'],$i['cta']['new_tab'])],$data['items']);
        } else {
            $out['mode'] = count($data['items']) > 1 ? 'carousel' : 'single';
            $out['items'] = array_map(static fn($i) => $i + ['link'=>'','new_tab'=>false],$data['items']);
        }
        return $out;
    }
}
