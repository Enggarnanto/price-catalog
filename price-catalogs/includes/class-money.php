<?php
defined('ABSPATH') || exit;

final class LPM_Money {
    public const CURRENCIES=['IDR'=>'Rp','USD'=>'US$','EUR'=>'€','GBP'=>'£','SGD'=>'S$','MYR'=>'RM','AUD'=>'A$','JPY'=>'¥'];
    public static function defaults(array $headers): array {
        $columns=[];foreach($headers as $i=>$header)if(preg_match('/harga|price|biaya|tarif/i',$header))$columns[]=$i;
        return ['currency'=>'IDR','decimals'=>0,'number_format'=>'id-ID','price_columns'=>$columns,'mobile_orientation'=>'inherit','freeze_first_column'=>true];
    }
    public static function validate($options,int $width): void {
        $keys=array_keys(self::defaults([]));$actual=is_array($options)?array_keys($options):[];sort($keys);sort($actual);
        if($actual!==$keys)throw new InvalidArgumentException('Pengaturan nominal/mobile tidak lengkap.');
        if(!is_string($options['currency'])||!isset(self::CURRENCIES[$options['currency']])||!in_array($options['decimals'],[0,2],true)||!in_array($options['number_format'],['id-ID','en-US'],true)||!in_array($options['mobile_orientation'],['inherit','vertical','horizontal'],true)||!is_bool($options['freeze_first_column']))throw new InvalidArgumentException('Pengaturan mata uang/mobile tidak valid.');
        if(!is_array($options['price_columns'])||!array_is_list($options['price_columns'])||count(array_unique($options['price_columns'],SORT_REGULAR))!==count($options['price_columns']))throw new InvalidArgumentException('Kolom nominal tidak valid.');
        foreach($options['price_columns'] as $column)if(!is_int($column)||$column<0||$column>=$width)throw new InvalidArgumentException('Kolom nominal di luar tabel.');
    }
    public static function format(string $text,array $options): string {
        $symbol=self::CURRENCIES[$options['currency']];$value=trim($text);
        foreach([$options['currency'],$symbol] as $prefix)if(str_starts_with($value,$prefix)){$value=trim(substr($value,strlen($prefix)));break;}
        $id=$options['number_format']==='id-ID';$group=$id?'\.':',';$decimal=$id?',':'\.';
        if(!preg_match('/^-?(?:\d+|\d{1,3}(?:'.$group.'\d{3})+)(?:'.$decimal.'\d{1,2})?$/D',$value))return $text;
        $number=(float)str_replace($id?',':'.','.',str_replace($id?'.':',','',$value));
        if(!is_finite($number)||abs($number)>999999999999999)return $text;
        return $symbol.' '.number_format($number,$options['decimals'],$id?',':'.',$id?'.':',');
    }
}
