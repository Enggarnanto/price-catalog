<?php
defined('ABSPATH') || exit;
final class LPM_CSV {
    public static function parse(string $csv, string $delimiter=',', bool $header=true): array {
        if (strlen($csv)>2097152 || !in_array($delimiter,[',',';'],true) || wp_check_invalid_utf8($csv)!==$csv || str_contains($csv,"\0")) { throw new InvalidArgumentException('CSV harus UTF-8, maksimal 2 MiB, delimiter koma/titik koma.'); }
        $csv=preg_replace('/^\xEF\xBB\xBF/','',$csv);
        // fgetcsv accepts unterminated quotes; reject malformed input before parsing.
        $quoted=false;$start=true;$closed=false;
        for($i=0,$n=strlen($csv);$i<$n;$i++){
            $ch=$csv[$i];
            if($quoted){if($ch==='"'){if($i+1<$n&&$csv[$i+1]==='"'){$i++;}else{$quoted=false;$closed=true;}}continue;}
            if($ch===$delimiter||$ch==="\n"||$ch==="\r"){$start=true;$closed=false;continue;}
            if($ch==='"'){if(!$start){throw new InvalidArgumentException('Quote CSV tidak valid pada offset '.$i.'.');}$quoted=true;$start=false;continue;}
            if($closed&&!ctype_space($ch)){throw new InvalidArgumentException('Karakter setelah quoted field tidak valid.');}
            $start=false;
        }
        if($quoted){throw new InvalidArgumentException('CSV memiliki quoted field yang belum ditutup.');}
        $stream=fopen('php://temp','w+'); fwrite($stream,$csv); rewind($stream); $records=[]; $columns=null; $record=0;
        try {
            while (($row=fgetcsv($stream,0,$delimiter,'"',''))!==false) {
                $record++;
                if ($row===[null]) { continue; }
                $columns??=count($row);
                if (count($row)!==$columns) { throw new InvalidArgumentException('Jumlah kolom tidak konsisten pada record CSV '.$record.'.'); }
                if ($columns>20 || count($records)>=201) { throw new InvalidArgumentException('CSV maksimal 20 kolom dan 200 baris data.'); }
                foreach ($row as $value) { LPM_Content::text($value,4000); }
                $records[]=$row;
            }
        } finally { fclose($stream); }
        if (!$records) { throw new InvalidArgumentException('CSV kosong.'); }
        $headers=$header?array_shift($records):array_map(static fn($i)=>'Kolom '.$i,range(1,$columns));
        foreach ($headers as $value) { LPM_Content::text($value,200); }
        if (count($records)>200) { throw new InvalidArgumentException('Maksimal 200 baris data.'); }
        $matrix=['headers'=>$headers,'rows'=>array_map(static fn($r)=>array_map([LPM_Content::class,'cell'],$r),$records)];
        if (strlen(LPM_Content::json($matrix))>LPM_Content::MAX_BYTES-16384) { throw new InvalidArgumentException('Hasil CSV melebihi batas snapshot 1 MiB.'); }
        return $matrix+['count'=>count($records),'columns'=>$columns];
    }
}
