<?php
declare(strict_types=1);

namespace Flexihash\Tests;

use Flexihash\Flexihash;
use Flexihash\Tests\Hasher\MockHasher;

/**
 * @author Paul Annesley
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class FlexihashTest extends \PHPUnit\Framework\TestCase
{
    public function testGetAllNodesEmpty(): void
    {
        $hashSpace = new Flexihash();
        $this->assertEquals($hashSpace->getAllNodes(), []);
    }

    public function testAddNodeThrowsExceptionOnDuplicateNode(): void
    {
        $hashSpace = new Flexihash();
        $hashSpace->addNode('t-a');
        $this->expectException('\Exception');
        $hashSpace->addNode('t-a');
    }

    public function testAddNodeAndGetAllNodes(): void
    {
        $hashSpace = new Flexihash();
        $hashSpace
            ->addNode('t-a')
            ->addNode('t-b')
            ->addNode('t-c')
            ;

        $this->assertEquals($hashSpace->getAllNodes(), ['t-a', 't-b', 't-c']);
    }

    public function testAddNodesAndGetAllNodes(): void
    {
        $nodes = ['t-a', 't-b', 't-c'];

        $hashSpace = new Flexihash();
        $hashSpace->addNodes($nodes);
        $this->assertEquals($hashSpace->getAllNodes(), $nodes);
    }

    public function testRemoveNode(): void
    {
        $hashSpace = new Flexihash();
        $hashSpace
            ->addNode('t-a')
            ->addNode('t-b')
            ->addNode('t-c')
            ->removeNode('t-b')
            ;
        $this->assertEquals($hashSpace->getAllNodes(), ['t-a', 't-c']);
    }

    public function testRemoveNodeFailsOnMissingNode(): void
    {
        $hashSpace = new Flexihash();
        $this->expectException('\Exception');
        $hashSpace->removeNode('not-there');
    }

    public function testHashSpaceRepeatableLookups(): void
    {
        $hashSpace = new Flexihash();
        foreach (range(1, 10) as $i) {
            $hashSpace->addNode("node$i");
        }

        $this->assertEquals($hashSpace->lookup('t1'), $hashSpace->lookup('t1'));
        $this->assertEquals($hashSpace->lookup('t2'), $hashSpace->lookup('t2'));
    }

    public function testHashSpaceLookupListEmpty(): void
    {
        $hashSpace = new Flexihash();
        $this->assertEmpty($hashSpace->getNode('t1', 2));
    }

    public function testHashSpaceLookupListNoNodes(): void
    {
        $this->expectException('\Exception');
        $this->expectExceptionMessage('No nodes exist');
        $hashSpace = new Flexihash();
        $hashSpace->lookup('t1');
    }

    public function testHashSpaceLookupListNo(): void
    {
        $this->expectException('\Exception');
        $this->expectExceptionMessage('Invalid count requested');
        $hashSpace = new Flexihash();
        $hashSpace->getNode('t1', 0);
    }

    public function testHashSpaceLookupsAreValidNodes(): void
    {
        $nodes = [];
        foreach (range(1, 10) as $i) {
            $nodes [] = "node$i";
        }

        $hashSpace = new Flexihash();
        $hashSpace->addNodes($nodes);

        foreach (range(1, 10) as $i) {
            $this->assertTrue(
                in_array($hashSpace->lookup("r$i"), $nodes),
                'node must be in list of nodes'
            );
        }
    }

    public function testHashSpaceConsistentLookupsAfterAddingAndRemoving(): void
    {
        $hashSpace = new Flexihash();
        foreach (range(1, 10) as $i) {
            $hashSpace->addNode("node$i");
        }

        $results1 = [];
        foreach (range(1, 100) as $i) {
            $results1 [] = $hashSpace->lookup("t$i");
        }

        $hashSpace
            ->addNode('new-node')
            ->removeNode('new-node')
            ->addNode('new-node')
            ->removeNode('new-node')
            ;

        $results2 = [];
        foreach (range(1, 100) as $i) {
            $results2 [] = $hashSpace->lookup("t$i");
        }

        // This is probably optimistic, as adding/removing a node may
        // clobber existing nodes and is not expected to restore them.
        $this->assertEquals($results1, $results2);
    }

    public function testHashSpaceConsistentLookupsWithNewInstance(): void
    {
        $hashSpace1 = new Flexihash();
        foreach (range(1, 10) as $i) {
            $hashSpace1->addNode("node$i");
        }
        $results1 = [];
        foreach (range(1, 100) as $i) {
            $results1 [] = $hashSpace1->lookup("t$i");
        }

        $hashSpace2 = new Flexihash();
        foreach (range(1, 10) as $i) {
            $hashSpace2->addNode("node$i");
        }
        $results2 = [];
        foreach (range(1, 100) as $i) {
            $results2 [] = $hashSpace2->lookup("t$i");
        }

        $this->assertEquals($results1, $results2);
    }

    public function testGetMultipleNodes(): void
    {
        $hashSpace = new Flexihash();
        foreach (range(1, 10) as $i) {
            $hashSpace->addNode("node$i");
        }

        $nodes = $hashSpace->getNode('resource', 2);

        $this->assertIsArray($nodes);
        $this->assertEquals(count($nodes), 2);
        $this->assertNotEquals($nodes[0], $nodes[1]);
    }

    public function testGetMultipleNodesWithOnlyOneNode(): void
    {
        $hashSpace = new Flexihash();
        $hashSpace->addNode('single-node');

        $nodes = $hashSpace->getNode('resource', 2);

        $this->assertIsArray($nodes);
        $this->assertEquals(count($nodes), 1);
        $this->assertEquals($nodes[0], 'single-node');
    }

    public function testGetMoreNodesThanExist(): void
    {
        $hashSpace = new Flexihash();
        $hashSpace->addNode('node1');
        $hashSpace->addNode('node2');

        $nodes = $hashSpace->getNode('resource', 4);

        $this->assertIsArray($nodes);
        $this->assertEquals(count($nodes), 2);
        $this->assertNotEquals($nodes[0], $nodes[1]);
    }

    public function testGetMultipleNodesNeedingToLoopToStart(): void
    {
        $mockHasher = new MockHasher();
        $hashSpace = new Flexihash($mockHasher, 1);

        $mockHasher->setHashValue(10);
        $hashSpace->addNode('t1');

        $mockHasher->setHashValue(20);
        $hashSpace->addNode('t2');

        $mockHasher->setHashValue(30);
        $hashSpace->addNode('t3');

        $mockHasher->setHashValue(40);
        $hashSpace->addNode('t4');

        $mockHasher->setHashValue(50);
        $hashSpace->addNode('t5');

        $mockHasher->setHashValue(35);
        $nodes = $hashSpace->getNode('resource', 4);

        $this->assertEquals($nodes, ['t4', 't5', 't1', 't2']);
    }

    public function testGetMultipleNodesWithoutGettingAnyBeforeLoopToStart(): void
    {
        $mockHasher = new MockHasher();
        $hashSpace = new Flexihash($mockHasher, 1);

        $mockHasher->setHashValue(10);
        $hashSpace->addNode('t1');

        $mockHasher->setHashValue(20);
        $hashSpace->addNode('t2');

        $mockHasher->setHashValue(30);
        $hashSpace->addNode('t3');

        $mockHasher->setHashValue(100);
        $nodes = $hashSpace->getNode('resource', 2);

        $this->assertEquals($nodes, ['t1', 't2']);
    }

    public function testGetMultipleNodesWithoutNeedingToLoopToStart(): void
    {
        $mockHasher = new MockHasher();
        $hashSpace = new Flexihash($mockHasher, 1);

        $mockHasher->setHashValue(10);
        $hashSpace->addNode('t1');

        $mockHasher->setHashValue(20);
        $hashSpace->addNode('t2');

        $mockHasher->setHashValue(30);
        $hashSpace->addNode('t3');

        $mockHasher->setHashValue(15);
        $nodes = $hashSpace->getNode('resource', 2);

        $this->assertEquals($nodes, ['t2', 't3']);
    }

    public function testFallbackPrecedenceWhenServerRemoved(): void
    {
        $mockHasher = new MockHasher();
        $hashSpace = new Flexihash($mockHasher, 1);

        $mockHasher->setHashValue(10);
        $hashSpace->addNode('t1');

        $mockHasher->setHashValue(20);
        $hashSpace->addNode('t2');

        $mockHasher->setHashValue(30);
        $hashSpace->addNode('t3');

        $mockHasher->setHashValue(15);

        $this->assertEquals($hashSpace->lookup('resource'), 't2');
        $this->assertEquals(
            $hashSpace->getNode('resource', 3),
            ['t2', 't3', 't1']
        );

        $hashSpace->removeNode('t2');

        $this->assertEquals($hashSpace->lookup('resource'), 't3');
        $this->assertEquals(
            $hashSpace->getNode('resource', 3),
            ['t3', 't1']
        );

        $hashSpace->removeNode('t3');

        $this->assertEquals($hashSpace->lookup('resource'), 't1');
        $this->assertEquals(
            $hashSpace->getNode('resource', 3),
            ['t1']
        );
    }

    /**
     * Does the __toString method behave as we expect.
     *
     * @author Dom Morgan <dom@d3r.com>
     */
    public function testHashSpaceToString(): void
    {
        $mockHasher = new MockHasher();
        $hashSpace = new Flexihash($mockHasher, 1);
        $hashSpace->addNode('t1');
        $hashSpace->addNode('t2');

        $this->assertSame(
            $hashSpace->__toString(),
            'Flexihash\Flexihash{nodes:[t1,t2]}'
        );
    }
}
