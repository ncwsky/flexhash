<?php
declare(strict_types=1);


namespace FlexHash\Tests;

use FlexHash\FlexHash;

require __DIR__ . '/../vendor/autoload.php';

$hashRing = new FlexHash(null, 3);
$hashRing->addNode("node1");
$hashRing->addNode("node2");
$hashRing->addNode("node3",10);
var_dump($hashRing->getNodes('a'));

$data = [];
foreach ($hashRing->nodeToPoint as $node => $points) {
    foreach ($points as $point) {
        $data[] = ["value" => $point, "name" => $node];
    }
}
//https://echarts.apache.org/examples/zh/editor.html?c=pie-borderRadius
$js = file_get_contents(__DIR__.'/pie.js');

file_put_contents(__DIR__.'/pie_data.js', str_replace('//pie_data',json_encode($data), $js));
echo $hashRing, PHP_EOL;
