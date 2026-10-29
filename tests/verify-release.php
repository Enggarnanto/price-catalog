<?php
require __DIR__.'/bootstrap.php';
if(empty($argv[1])||empty($argv[2]))throw new InvalidArgumentException('Usage: verify-release.php release.zip release.json');
$data=LPM_Updater::validate(json_decode(file_get_contents($argv[2]),true,32,JSON_THROW_ON_ERROR));
LPM_Updater::verify_zip($argv[1],$data);echo 'PASS release ZIP matches checksum, version and plugin structure.' . PHP_EOL;
