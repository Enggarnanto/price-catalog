<?php
// Reuse standalone WordPress API doubles; parse with the actual plugin validators.
ob_start();require dirname(__DIR__).'/tests/shortcode-options.php';ob_end_clean();
require_once dirname(__DIR__).'/price-catalogs/includes/class-money.php';
require_once dirname(__DIR__).'/price-catalogs/includes/class-json-import.php';
$root=dirname(__DIR__).'/PRICELIST_GALIH/';
$files=['SEMUA-PRICELIST-LATISEDU-TUTORINDO.json'=>108,'LATISEDU/SEMUA-PRICELIST-LATISEDU.json'=>54,'TUTORINDO/SEMUA-PRICELIST-TUTORINDO.json'=>54];
foreach($files as $file=>$expected){$records=LPM_JSON_Import::parse(file_get_contents($root.$file));if(count($records)!==$expected)throw new RuntimeException('Wrong count: '.$file);echo 'PASS '.$file.': '.count($records).' valid import records'.PHP_EOL;}
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));$count=0;
foreach($iterator as $file){if(!in_array($file->getFilename(),['PGC.json','EXCELLENT.json','BRILLIANT.json'],true))continue;$records=LPM_JSON_Import::parse(file_get_contents($file->getPathname()));if(count($records)!==1)throw new RuntimeException('Expected single resource');$count++;}
if($count!==108)throw new RuntimeException('Expected 108 individual program files');
echo 'PASS all 108 individual JSON files validate with the actual import parser and content schema.'.PHP_EOL;
