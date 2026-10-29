<?php
defined('ABSPATH') || exit;

final class LPM_JSON_Import {
    public const MAX_BYTES=10485760;
    private static function number($value,string $label): int {
        if((!is_int($value)&&!(is_string($value)&&ctype_digit($value)))||(int)$value<0||(string)(int)$value!==(string)$value)throw new InvalidArgumentException('Angka tidak valid: '.$label);
        return (int)$value;
    }
    public static function record(array $item,string $type): array {
        if(!in_array($type,['price_table','price_catalog'],true))throw new InvalidArgumentException('Jenis resource JSON tidak didukung.');
        if(!is_array($item['metadata']??null))throw new InvalidArgumentException('Metadata resource JSON tidak ditemukan.');
        $name=trim(LPM_Content::text($item['metadata']['name']??null,200));if($name==='')throw new InvalidArgumentException('Nama resource JSON wajib diisi.');
        $description=LPM_Content::text($item['metadata']['description']??'',1000);
        if(!is_array($item['content']??null))throw new InvalidArgumentException('Konten resource JSON tidak valid.');
        $content=LPM_Content::upgrade($type,$item['content']);$warnings=[];
        if($type==='price_catalog')foreach($content['items'] as $i=>&$image){
            if(!LPM_Content::media_exists($image['attachment_id'])||wp_get_attachment_url($image['attachment_id'])!==$image['image_url']){
                $image['attachment_id']=0;$warnings[]='Gambar '.($i+1).' perlu dipilih ulang dari Media Library sebelum publish.';
            }
        }unset($image);
        return ['source_id'=>self::number($item['resource_id']??$item['source_id']??0,'resource_id'),'type'=>$type,'metadata'=>['name'=>$name,'description'=>$description],'content'=>$content,'warnings'=>$warnings];
    }
    public static function parse(string $json): array {
        if(strlen($json)>self::MAX_BYTES||wp_check_invalid_utf8($json)!==$json)throw new InvalidArgumentException('JSON harus UTF-8 dan maksimal 10 MiB.');
        $document=json_decode(preg_replace('/^\xEF\xBB\xBF/','',$json),true,64,JSON_THROW_ON_ERROR);
        if(!is_array($document)||array_is_list($document))throw new InvalidArgumentException('Format dokumen JSON tidak didukung. Gunakan file export plugin.');
        if(($document['format']??'')==='lpm-export'){
            if(($document['version']??null)!==1)throw new InvalidArgumentException('Versi format export JSON tidak didukung.');
            $type=$document['resource_type']??'';$items=$document['resources']??null;
        }elseif(isset($document['content'],$document['metadata'])){
            $type=$document['resource_type']??(isset($document['content']['headers'])?'price_table':(isset($document['content']['mode'],$document['content']['items'])?'price_catalog':''));$items=[$document];
        }else throw new InvalidArgumentException('Gunakan JSON hasil Export Data atau Unduh Cadangan plugin.');
        if(!is_string($type)||!is_array($items)||!array_is_list($items)||count($items)<1||count($items)>200)throw new InvalidArgumentException('JSON membutuhkan 1–200 resource.');
        $records=[];$ids=[];
        foreach($items as $index=>$item){
            if(!is_array($item))throw new InvalidArgumentException('Resource ke-'.($index+1).' tidak valid.');
            try{$record=self::record($item,$type);}catch(Throwable $e){throw new InvalidArgumentException('Resource ke-'.($index+1).': '.$e->getMessage());}
            if($record['source_id']&&isset($ids[$record['source_id']]))throw new InvalidArgumentException('ID sumber duplikat: '.$record['source_id']);
            $ids[$record['source_id']]=true;$records[]=$record;
        }
        return $records;
    }
    private static function signature(array $record,array $target): string {
        return hash_hmac('sha256',LPM_Content::json(['record'=>$record,'target'=>$target,'user'=>get_current_user_id()]),wp_salt('nonce'));
    }
    public static function preview(string $json,string $mode): array {
        LPM_Service::authorize();if(!in_array($mode,['new','existing'],true))throw new InvalidArgumentException('Mode import tidak valid.');
        $records=self::parse($json);$items=[];
        foreach($records as $index=>$record){
            $target=['id'=>0,'revision'=>0,'base'=>0,'production'=>0];$error='';$target_name='';
            if($mode==='existing')try{
                if(!$record['source_id'])throw new InvalidArgumentException('ID sumber diperlukan untuk memperbarui item.');
                $editor=LPM_Service::editor($record['source_id']);$r=$editor['resource'];
                if($r['resource_type']!==$record['type'])throw new InvalidArgumentException('Jenis resource tujuan berbeda.');
                $target=['id'=>(int)$r['id'],'revision'=>(int)$r['draft_revision'],'base'=>(int)$editor['base'],'production'=>(int)$r['production_version_id']];
                $target_name=$r['name'];if($target_name!==$record['metadata']['name'])$record['warnings'][]='Nama tujuan berbeda dari file. Pastikan ID memang berasal dari situs ini.';
                if($r['draft_data']!==null)$record['warnings'][]='Draft yang tersimpan pada ID ini akan diganti.';
            }catch(Throwable $e){$error=$e->getMessage();}
            $items[]=['index'=>$index,'record'=>$record,'target'=>$target,'target_name'=>$target_name,'error'=>$error,'preview_token'=>$error?'':self::signature($record,$target)];
        }
        return ['items'=>$items,'mode'=>$mode,'file_hash'=>hash('sha256',$json)];
    }
    public static function apply(array $item,string $token,string $operation): array {
        LPM_Service::authorize();
        if(!is_array($item['record']??null)||!is_array($item['target']??null)||!is_string($item['record']['type']??null))throw new InvalidArgumentException('Preview import tidak valid.');
        $record=self::record($item['record'],$item['record']['type']);
        // Warnings are preview annotations, not editable resource data.
        $record['warnings']=$item['record']['warnings']??[];
        $target=[];foreach(['id','revision','base','production'] as $key)$target[$key]=self::number($item['target'][$key]??null,$key);
        if(!hash_equals(self::signature($record,$target),$token))throw new LPM_Conflict('Data atau target berubah. Jalankan Preview JSON lagi.');
        // Display-only warnings do not affect a persisted operation's identity.
        unset($record['warnings']);return LPM_Service::import_draft($record,$target,$operation);
    }
}
