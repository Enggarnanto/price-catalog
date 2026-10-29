<?php
defined('ABSPATH') || exit;
final class LPM_Styling {
    public static function defaults(): array {
        return ['accent'=>'#2563eb','header_bg'=>'#172554','header_text'=>'#ffffff','background'=>'#ffffff','text'=>'#172033',
            'border'=>'#dbe2ec','stripe'=>'#f4f7fb','button_bg'=>'#2563eb','button_text'=>'#ffffff','padding'=>14,'font_size'=>15,'radius'=>8,'zebra'=>true];
    }
    public static function validate($values): array {
        if (!is_array($values) || count($values)!==count(self::defaults()) || array_diff_key($values,self::defaults())) { throw new InvalidArgumentException('Field styling tidak valid.'); }
        foreach (self::defaults() as $key=>$default) {
            $value=$values[$key];
            if (is_string($default) && (!is_string($value)||!preg_match('/^#[a-fA-F0-9]{6}$/D',$value))) { throw new InvalidArgumentException('Warna harus hex #RRGGBB.'); }
            if (is_int($default) && (!is_int($value)||$value<($key==='font_size'?10:0)||$value>($key==='font_size'?32:32))) { throw new InvalidArgumentException('Ukuran styling di luar batas.'); }
            if (is_bool($default) && !is_bool($value)) { throw new InvalidArgumentException('Toggle styling tidak valid.'); }
        }
        return $values;
    }
    public static function get(): array { return array_replace(self::defaults(),(array)get_option('lpm_styling',[])); }
    public static function save(array $values): array { LPM_Service::authorize(); $values=self::validate($values); update_option('lpm_styling',$values,false); return $values; }
}
