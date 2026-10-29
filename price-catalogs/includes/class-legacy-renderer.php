<?php
defined('ABSPATH') || exit;

final class LPM_Legacy_Renderer {
    private static function cta(array $cta): string {
        if ($cta['url'] === '' || $cta['label'] === '') { return ''; }
        return '<a class="lpm-cta" href="' . esc_url($cta['url']) . '"' . ($cta['new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . esc_html($cta['label']) . '</a>';
    }

    public static function render(string $type, array $content): string {
        $content = LPM_Legacy_Content::validate($type, $content);
        $out = '<section class="lpm-resource" style="--lpm-accent:' . esc_attr($content['style']['accent']) . ';--lpm-columns:' . (int) $content['style']['columns'] . '">';
        $out .= '<h2>' . esc_html($content['title']) . '</h2><p class="lpm-description">' . nl2br(esc_html($content['description'])) . '</p>';
        if ($type === 'price_table') {
            $out .= '<div class="lpm-scroll"><table class="lpm-table"><thead><tr><th scope="col">Layanan / Produk</th><th scope="col">Harga</th><th scope="col">Keterangan</th><th scope="col">Tindakan</th></tr></thead><tbody>';
            foreach ($content['items'] as $item) {
                $out .= '<tr><th scope="row">' . esc_html($item['title']) . '</th><td>' . esc_html($item['price']) . '</td><td>' . nl2br(esc_html($item['description'])) . '</td><td>' . self::cta($item['cta']) . '</td></tr>';
            }
            $out .= '</tbody></table></div>';
        } else {
            $out .= '<div class="lpm-grid">';
            foreach ($content['items'] as $item) {
                $out .= '<article class="lpm-card"><figure>';
                if (LPM_Legacy_Content::media_exists($item['attachment_id'])) {
                    $out .= '<img loading="lazy" decoding="async" src="' . esc_url($item['image_url']) . '" alt="' . esc_attr($item['alt']) . '">';
                } else { $out .= '<div class="lpm-placeholder" role="img" aria-label="Gambar tidak tersedia">Gambar tidak tersedia</div>'; }
                $out .= '<figcaption>' . esc_html($item['caption']) . '</figcaption></figure><div class="lpm-card-body"><h3>' . esc_html($item['title']) . '</h3><p class="lpm-price">' . esc_html($item['price']) . '</p><p>' . nl2br(esc_html($item['description'])) . '</p>' . self::cta($item['cta']) . '</div></article>';
            }
            $out .= '</div>';
        }
        return $out . self::cta($content['cta']) . '</section>';
    }

    public static function shortcode(string $type, array $atts): string {
        try {
            // Only the resource ID is used. A caller cannot select a draft or historical version.
            $id = isset($atts['id']) && ctype_digit((string) $atts['id']) ? (int) $atts['id'] : 0;
            if (!$id) { return ''; }
            $resource = LPM_Service::resource($id);
            if ($resource['resource_type'] !== $type || !$resource['production_version_id']) { return ''; }
            return self::render($type, LPM_Service::snapshot(LPM_Service::version($resource, (int) $resource['production_version_id'])));
        } catch (Throwable $e) {
            error_log('LPM render unavailable: resource=' . (int) ($id ?? 0) . ' category=' . get_class($e));
            return '';
        }
    }
}
