<?php
declare(strict_types=1);

namespace FlexHash\Tests;

use FlexHash\FlexHash;
use FlexHash\Hasher\Crc32Hasher;
use FlexHash\Hasher\Md5Hasher;

/**
 * Benchmarks, not really tests.
 *
 * @author Paul Annesley
 * @group benchmark
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class BenchmarkTest extends \PHPUnit\Framework\TestCase
{
    private $nodes = 10;
    private $lookups = 1000;

    /**
     * @param $message
     */
    public function dump($message)
    {
        echo $message . "\n";
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testAddNodeWithNonConsistentHash()
    {
        $results1 = [];
        foreach (range(1, $this->lookups) as $i) {
            $results1[$i] = $this->basicHash("t$i", 10);
        }

        $results2 = [];
        foreach (range(1, $this->lookups) as $i) {
            $results2[$i] = $this->basicHash("t$i", 11);
        }

        $differences = 0;
        foreach (range(1, $this->lookups) as $i) {
            if ($results1[$i] !== $results2[$i]) {
                ++$differences;
            }
        }

        $percent = round($differences / $this->lookups * 100);

        $this->dump("NonConsistentHash: {$percent}% of lookups changed " .
            "after adding a node to the existing {$this->nodes}");
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRemoveNodeWithNonConsistentHash()
    {
        $results1 = [];
        foreach (range(1, $this->lookups) as $i) {
            $results1[$i] = $this->basicHash("t$i", 10);
        }

        $results2 = [];
        foreach (range(1, $this->lookups) as $i) {
            $results2[$i] = $this->basicHash("t$i", 9);
        }

        $differences = 0;
        foreach (range(1, $this->lookups) as $i) {
            if ($results1[$i] !== $results2[$i]) {
                ++$differences;
            }
        }

        $percent = round($differences / $this->lookups * 100);

        $this->dump("NonConsistentHash: {$percent}% of lookups changed " .
            "after removing 1 of {$this->nodes} nodes");
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testHopeAddingNodeDoesNotChangeMuchWithCrc32Hasher()
    {
        $hashSpace = new FlexHash();
        foreach (range(1, $this->nodes) as $i) {
            $hashSpace->addNode("node$i");
        }

        $results1 = [];
        foreach (range(1, $this->lookups) as $i) {
            $results1[$i] = $hashSpace->lookup("t$i");
        }

        $hashSpace->addNode('node-new');

        $results2 = [];
        foreach (range(1, $this->lookups) as $i) {
            $results2[$i] = $hashSpace->lookup("t$i");
        }

        $differences = 0;
        foreach (range(1, $this->lookups) as $i) {
            if ($results1[$i] !== $results2[$i]) {
                ++$differences;
            }
        }

        $percent = round($differences / $this->lookups * 100);

        $this->dump("ConsistentHash: {$percent}% of lookups changed " .
            "after adding a node to the existing {$this->nodes}");
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testHopeRemovingNodeDoesNotChangeMuchWithCrc32Hasher()
    {
        $hashSpace = new FlexHash();
        foreach (range(1, $this->nodes) as $i) {
            $hashSpace->addNode("node$i");
        }

        $results1 = [];
        foreach (range(1, $this->lookups) as $i) {
            $results1[$i] = $hashSpace->lookup("t$i");
        }

        $hashSpace->removeNode('node1');

        $results2 = [];
        foreach (range(1, $this->lookups) as $i) {
            $results2[$i] = $hashSpace->lookup("t$i");
        }

        $differences = 0;
        foreach (range(1, $this->lookups) as $i) {
            if ($results1[$i] !== $results2[$i]) {
                ++$differences;
            }
        }

        $percent = round($differences / $this->lookups * 100);

        $this->dump("ConsistentHash: {$percent}% of lookups changed " .
            "after removing 1 of {$this->nodes} nodes");
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testHashDistributionWithCrc32Hasher()
    {
        $hashSpace = new FlexHash();

        foreach (range(1, $this->nodes) as $i) {
            $hashSpace->addNode("node$i");
        }

        $results = [];
        foreach (range(1, $this->lookups) as $i) {
            $results[$i] = $hashSpace->lookup("t$i");
        }

        $distribution = [];
        foreach ($hashSpace->getAllNodes() as $node) {
            $distribution[$node] = count(array_keys($results, $node));
        }

        $this->dump(sprintf(
            'Distribution of %d lookups per node (min/max/median/avg): %d/%d/%d/%d',
            $this->lookups / $this->nodes,
            min($distribution),
            max($distribution),
            round($this->median($distribution)),
            round(array_sum($distribution) / count($distribution))
        ));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testHasherSpeed()
    {
        $hashCount = 100000;

        $md5Hasher = new Md5Hasher();
        $crc32Hasher = new Crc32Hasher();

        $start = microtime(true);
        for ($i = 0; $i < $hashCount; ++$i) {
            $md5Hasher->hash("test$i");
        }
        $timeMd5 = microtime(true) - $start;

        $start = microtime(true);
        for ($i = 0; $i < $hashCount; ++$i) {
            $crc32Hasher->hash("test$i");
        }
        $timeCrc32 = microtime(true) - $start;

        $this->dump(sprintf(
            'Hashers timed over %d hashes (MD5 / CRC32): %f / %f',
            $hashCount,
            $timeMd5,
            $timeCrc32
        ));
    }

    // ----------------------------------------

    private function basicHash($value, $nodes)
    {
        return abs(crc32($value) % $nodes);
    }

    /**
     * @param array $values list of numeric values
     * @return int
     */
    private function median($values)
    {
        $values = array_values($values);
        sort($values);

        $count = count($values);
        $middleFloor = floor($count / 2);

        if ($count % 2 == 1) {
            return $values[$middleFloor];
        } else {
            return ($values[$middleFloor] + $values[$middleFloor + 1]) / 2;
        }
    }
}
