<?php
declare(strict_types=1);


namespace FlexHash\Tests;

use FlexHash\FlexHash;
use FlexHash\Tests\Hasher\MockHasher;

require __DIR__ . '/../vendor/autoload.php';

//CRC-16 CCITT
function crc16($data)
{
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
        $x = (($crc >> 8) ^ ord($data[$i])) & 0xFF;
        $x ^= $x >> 4;
        $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
    }
    return $crc;
}

/*//CRC-16 MODBUS
function crc16($data)
{
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
        $crc ^= ord($data[$i]);

        for ($j = 8; $j != 0; $j--) {
            if (($crc & 0x0001) != 0) {
                $crc >>= 1;
                $crc ^= 0xA001;
            } else
                $crc >>= 1;
        }
    }
    return $crc;
}*/



$hashRing = new FlexHash(null, 10);
$hashRing->addNode("node1");
$hashRing->addNode("node2");
//$hashRing->addNode("node3");
var_dump($hashRing->getNodes('a',1), $hashRing->getNodes('/data3/20071101/0418/1452461287.634538ea1d0324.09128886'));
echo $hashRing,PHP_EOL;
//die();
$mockHasher = new MockHasher();
$hashSpace = new FlexHash($mockHasher, 1);

$mockHasher->setHashValue(10);
$hashSpace->addNode('t1');

$mockHasher->setHashValue(20);
$hashSpace->addNode('t2');

$mockHasher->setHashValue(30);
$hashSpace->addNode('t3');

$mockHasher->setHashValue(100);
$nodes = $hashSpace->getNodes('resource', 2);
var_dump($nodes); //['t1', 't2']


$data = [];
$positionToNode = $hashRing->getSortedPositionToNode();
foreach ($positionToNode as $point => $node) {
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
    //echo $key,PHP_EOL;
    $count[$hashRing->lookup($key)]++;
    if($i%10000==0) echo $i,PHP_EOL;
}
var_dump($count);
echo ' over '.(microtime(true) - $start),PHP_EOL;

