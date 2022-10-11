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
$hashRing->addNode("node2",5);
//$hashRing->addNode("node3");
var_dump($hashRing->getNodes('a',1));
echo $hashRing,PHP_EOL;

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

$arr = [1,5,10,35,68,70,89,100,102,234,254];
$v = $argv[1]??100;
$getCount = $argv[2]??1;
//$n = sortSearch(count($arr), function($i) use($arr, $v){ return $arr[$i-1]>=$v;})-1;
//var_dump($arr[$hashRing->circleSearch($arr, count($arr), $v)]);
//var_dump(locationPoint($arr, count($arr), $v, $getCount));
//var_dump($n, $arr[$n]);

function circleSearch($points, $pointCount, $resourcePoint, $getCount = 1)
{
    $j = $pointCount;
    for ($i = 0; $i < $j;) {
        $h = (int)(($i + $j) >> 1); //(int)floor(($i + $j) / 2);// avoid overflow when computing h
        // i ≤ h < j
        if ($points[$h] >= $resourcePoint) {
            $j = $h; // preserves f(j) == true
        } else {
            $i = $h + 1; // preserves f(i-1) == false
        }
    }
    if ($i == $pointCount) $i = 0;
    $result = [$points[$i]];
    if ($getCount > 1) {
        for ($n = 1; $n < $getCount; $n++) {
            if (++$i == $pointCount) $i = 0;
            $result[] = $points[$i];
        }
    }
    return $result;
}

function locationPoint($points, $pointCount, $resourcePoint, $getCount=1){
    $low = 0;
    $high = $pointCount - 1;
    $notfound = false;
    $results = [];
    // binary search of the first point greater than resource point
    while ($high >= $low || $notfound = true) {
        $probe = (int)floor(($high + $low) / 2);

        var_dump('('.$low . '+' . $high.')/2 = '. $probe);

        if ($notfound === false && $points[$probe] <= $resourcePoint) {
            $low = $probe + 1;
        } elseif ($probe === 0 || $resourcePoint > $points[$probe - 1] || $notfound === true) {
            if ($notfound) {
                // if not found is true, it means binary search failed to find any point greater
                // than ressource point, in this case, the last point is the bigest lower
                // point and first point is the next one after cycle
                $probe = 0;
            }

            $results[] = $points[$probe];

            if ($getCount > 1) {
                $maxIdx = $pointCount - 1;
                for ($i = $getCount - 1; $i > 0; --$i) {
                    if (++$probe > $maxIdx) {
                        $probe = 0; // cycle
                    }
                    $results[] = $points[$probe];
                }
            }

            break;
        } else {
            $high = $probe - 1;
        }
    }
    return $results;
}

die();
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
