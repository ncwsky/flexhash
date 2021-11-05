<?php
declare(strict_types=1);


namespace FlexHash\Tests;

use FlexHash\FlexHash;

require __DIR__ . '/../vendor/autoload.php';

$hashRing = new FlexHash(null, 512);
$hashRing->addNode("node1");
$hashRing->addNode("node2",5);
//$hashRing->addNode("node3");
var_dump($hashRing->getNodes('a',1));

$data = [];
foreach ($hashRing->pointToNode as $point => $node) {
    $data[] = ["value" => $point, "name" => $node];
}
//https://echarts.apache.org/examples/zh/editor.html?c=pie-borderRadius
$js = file_get_contents(__DIR__.'/pie.js');
file_put_contents(__DIR__.'/pie_data.js', str_replace('//pie_data',substr(json_encode($data),1,-1), $js));


$start = microtime(true);
$count = [
    'node1'=>0,
    'node2'=>0,
    'node3'=>0,
];
for ($i = 0; $i < 1000000; $i++) {
    $time = mt_rand(1167580800, 1636107617);
    $key = '/data'.mt_rand(1,3).'/' . date("Ymd/hi", $time) . '/' . mt_rand(1167580800, 1636107617) . '.' . uniqid('', true);
    $count[$hashRing->lookup($key)]++;
    if($i%10000==0) echo $i,PHP_EOL;
}
var_dump($count);
echo ' over '.(microtime(true) - $start),PHP_EOL;

exit;
echo $hashRing, PHP_EOL;
