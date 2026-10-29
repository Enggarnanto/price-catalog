<?php
defined('ABSPATH') || exit;

final class LPM_Legacy_Content {
    public const SCHEMA = 1;
    public const MAX_BYTES = 1048576;

    public static function initial(string $type): array {
        return ['title' => '', 'description' => '', 'style' => ['accent' => '#2563eb', 'columns' => 3],
            'cta' => ['label' => '', 'url' => '', 'new_tab' => false], 'items' => []];
    }

    private static function text($value, int $max): string {
        if (!is_string($value) || mb_strlen($value) > $max || wp_check_invalid_utf8($value) !== $value || preg_match('/[<>]/u', $value)) {
            throw new InvalidArgumentException('Teks tidak valid, terlalu panjang, atau mengandung markup HTML.');
        }
        return $value;
    }

    private static function keys(array $data, array $keys): void {
        $actual = array_keys($data); sort($actual); sort($keys);
        if ($actual !== $keys) { throw new InvalidArgumentException('Field konten tidak sesuai schema v1.'); }
    }

    private static function url($value): string {
        $value = self::text($value, 2048);
        if ($value !== '' && (!filter_var($value, FILTER_VALIDATE_URL) || !in_array(strtolower((string) wp_parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            throw new InvalidArgumentException('URL harus berupa HTTP atau HTTPS yang valid.');
        }
        return $value;
    }

    private static function cta($data): array {
        if (!is_array($data)) { throw new InvalidArgumentException('CTA tidak valid.'); }
        self::keys($data, ['label', 'url', 'new_tab']);
        $label = self::text($data['label'], 100); $url = self::url($data['url']);
        if (!is_bool($data['new_tab']) || (($label === '') !== ($url === ''))) {
            throw new InvalidArgumentException('CTA membutuhkan label dan URL, atau keduanya kosong.');
        }
        return ['label' => $label, 'url' => $url, 'new_tab' => $data['new_tab']];
    }

    public static function validate(string $type, $data, bool $publishing = false, int $schema = self::SCHEMA): array {
        if ($schema !== self::SCHEMA) { throw new InvalidArgumentException('Schema snapshot belum didukung.'); }
        if (!in_array($type, ['price_table', 'price_catalog'], true) || !is_array($data)) {
            throw new InvalidArgumentException('Tipe atau konten resource tidak valid.');
        }
        if (strlen(self::json($data)) > self::MAX_BYTES) { throw new InvalidArgumentException('Payload maksimal 1 MiB.'); }
        self::keys($data, ['title', 'description', 'style', 'cta', 'items']);
        $title = self::text($data['title'], 200);
        $description = self::text($data['description'], 4000);
        if (!is_array($data['style'])) { throw new InvalidArgumentException('Style tidak valid.'); }
        self::keys($data['style'], ['accent', 'columns']);
        if (!is_string($data['style']['accent']) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $data['style']['accent']) ||
            !is_int($data['style']['columns']) || $data['style']['columns'] < 1 || $data['style']['columns'] > 4) {
            throw new InvalidArgumentException('Accent harus warna hex; kolom harus 1–4.');
        }
        if (!is_array($data['items']) || !array_is_list($data['items']) || count($data['items']) > 200) {
            throw new InvalidArgumentException('Items harus berupa daftar maksimal 200 item.');
        }
        if ($publishing && ($title === '' || !$data['items'])) { throw new InvalidArgumentException('Judul dan minimal satu item wajib sebelum publish.'); }
        foreach ($data['items'] as &$item) {
            if (!is_array($item)) { throw new InvalidArgumentException('Item tidak valid.'); }
            $keys = ['title', 'price', 'description', 'cta'];
            if ($type === 'price_catalog') { $keys = array_merge($keys, ['attachment_id', 'image_url', 'alt', 'caption']); }
            self::keys($item, $keys);
            self::text($item['title'], 200); self::text($item['price'], 100); self::text($item['description'], 2000);
            $item['cta'] = self::cta($item['cta']);
            if ($publishing && $item['title'] === '') { throw new InvalidArgumentException('Setiap item membutuhkan judul.'); }
            if ($type === 'price_catalog') {
                if (!is_int($item['attachment_id']) || $item['attachment_id'] < 1) { throw new InvalidArgumentException('Catalog membutuhkan attachment gambar WordPress.'); }
                self::url($item['image_url']); self::text($item['alt'], 300); self::text($item['caption'], 500);
                if ($publishing && (!self::media_exists($item['attachment_id']) || $item['image_url'] === '')) {
                    throw new InvalidArgumentException('Gambar wajib tidak tersedia. Pilih ulang gambar melalui draft.');
                }
            }
        }
        unset($item);
        return ['title' => $title, 'description' => $description, 'style' => $data['style'], 'cta' => self::cta($data['cta']), 'items' => $data['items']];
    }

    public static function media_exists(int $id): bool {
        return wp_attachment_is_image($id) && (bool) wp_get_attachment_url($id) && is_file((string) get_attached_file($id));
    }

    public static function json($data): string {
        if (is_array($data)) {
            if (!array_is_list($data)) { ksort($data); }
            foreach ($data as &$value) {
                if (is_array($value)) { $value = json_decode(self::json($value), true, 512, JSON_THROW_ON_ERROR); }
            }
            unset($value);
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function decode(string $json): array {
        if (strlen($json) > self::MAX_BYTES) { throw new InvalidArgumentException('Payload maksimal 1 MiB.'); }
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) { throw new InvalidArgumentException('Payload JSON tidak valid.'); }
        return $data;
    }

    public static function diff($before, $after, string $path = '$'): array {
        if ($before === $after) { return []; }
        if (!is_array($before) || !is_array($after)) { return [['field' => $path, 'before' => $before, 'after' => $after]]; }
        $out = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $next = $path . '.' . $key;
            if (!array_key_exists($key, $before) || !array_key_exists($key, $after)) {
                $out[] = ['field' => $next, 'before' => $before[$key] ?? null, 'after' => $after[$key] ?? null];
            } else { $out = array_merge($out, self::diff($before[$key], $after[$key], $next)); }
        }
        return $out;
    }
}
