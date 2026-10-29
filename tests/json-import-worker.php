<?php
require __DIR__.'/bootstrap.php';
$job=json_decode(file_get_contents(__DIR__.'/runtime/json-import-worker.json'),true,64,JSON_THROW_ON_ERROR);
while(microtime(true)<(float)$argv[1])usleep(10000);
echo wp_json_encode(LPM_JSON_Import::apply($job['item'],$job['item']['preview_token'],$job['operation_key']));
