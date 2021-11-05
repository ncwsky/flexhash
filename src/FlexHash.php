<?php
declare(strict_types=1);

namespace FlexHash;

use FlexHash\Hasher\HasherInterface;
use FlexHash\Hasher\Crc32Hasher;

/**
 * A simple consistent hashing implementation with pluggable hash algorithms.
 *
 * @author Paul Annesley
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class FlexHash
{
    /**
     * The number of points to hash each node to.
     *
     * @var int
     */
    private $replicas = 64;

    /**
     * The hash algorithm, encapsulated in a FlexHash_Hasher implementation.
     * @var object FlexHash_Hasher
     */
    private $hasher;

    /**
     * Internal counter for current number of nodes.
     * @var int
     */
    private $nodeCount = 0;

    /**
     * Internal map of points (hash outputs) to nodes.
     * @var array { point => node, ... }
     */
    public $pointToNode = [];

    /**
     * Internal map of nodes to lists of points that node is hashed to.
     * @var array { node => [ point, point, ... ], ... }
     */
    public $nodeToPoint = [];

    /**
     * Whether the internal map of points to nodes is already sorted.
     * @var bool
     */
    private $pointToNodeSorted = false;

    /**
     * Sorted points.
     *
     * @var array
     */
    private $sortedPoints = [];

    /**
     * Internal counter for current number of points.
     *
     * @var integer
     */
    private $pointCount = 0;

    /**
     * Constructor.
     * @param \FlexHash\Hasher\HasherInterface $hasher
     * @param int $replicas Amount of points to hash each node to.
     */
    public function __construct(HasherInterface $hasher = null, $replicas = null)
    {
        $this->hasher = $hasher ? $hasher : new Crc32Hasher();
        if (!empty($replicas)) {
            $this->replicas = $replicas;
        }
    }

    /**
     * Add a node.
     * @param $node
     * @param int $weight
     * @return $this
     * @throws \Exception
     */
    public function addNode($node, $weight = 1)
    {
        if (isset($this->nodeToPoint[$node])) {
            throw new \Exception("Node '$node' already exists.");
        }

        $this->nodeToPoint[$node] = [];

        // hash the node into multiple points
        for ($i = 0; $i < round($this->replicas * $weight); ++$i) {
            $point = $this->hasher->hash($node . $i);
            $this->pointToNode[$point] = $node; // lookup
            $this->nodeToPoint[$node] [] = $point; // node removal
        }

        $this->pointToNodeSorted = false;
        ++$this->nodeCount;

        return $this;
    }

    /**
     * Add a list of nodes.
     * @param $nodes
     * @param int $weight
     * @return $this
     * @throws \Exception
     */
    public function addNodes($nodes, $weight = 1)
    {
        foreach ($nodes as $node) {
            $this->addNode($node, $weight);
        }

        return $this;
    }

    /**
     * Remove a node.
     * @param $node
     * @return $this
     * @throws \Exception
     */
    public function removeNode($node)
    {
        if (!isset($this->nodeToPoint[$node])) {
            throw new \Exception("Node '$node' does not exist.");
        }

        foreach ($this->nodeToPoint[$node] as $point) {
            unset($this->pointToNode[$point]);
        }

        unset($this->nodeToPoint[$node]);

        $this->pointToNodeSorted = false;
        --$this->nodeCount;

        return $this;
    }

    /**
     * A list of all potential nodes.
     * @return array
     */
    public function getAllNodes(): array
    {
        return array_keys($this->nodeToPoint);
    }

    /**
     * Looks up the node for the given resource.
     * @param string $resource
     * @return string
     * @throws \Exception when no nodes defined
     */
    public function lookup($resource): string
    {
        $nodes = $this->getNodes($resource);
        if (empty($nodes)) {
            throw new \Exception('No nodes exist');
        }

        return $nodes[0];
    }

    /**
     * Get a list of nodes for the resource, in order of precedence.
     * Up to $getCount nodes are returned, less if there are fewer in total.
     *
     * @param string $resource
     * @param int $getCount The length of the list to return
     * @return array List of nodes
     * @throws \Exception when count is invalid
     */
    public function getNodes($resource, $getCount=1): array
    {
        if (!$getCount || $getCount<0) {
            throw new \Exception('Invalid count requested');
        }

        // handle no nodes
        if (empty($this->pointToNode)) {
            return [];
        }

        // optimize single node
        if ($this->nodeCount == 1) {
            return [current($this->pointToNode)];
        }

        // hash resource to a point
        $resourcePoint = $this->hasher->hash($resource);

        $results = [];

        $this->sortPointNodes();

        $points = $this->sortedPoints;
        $low = 0;
        $high = $this->pointCount - 1;
        $notfound = false;

        // binary search of the first point greater than resource point
        while ($high >= $low || $notfound = true) {
            $probe = (int)floor(($high + $low) / 2);

            if ($notfound === false && $points[$probe] <= $resourcePoint) {
                $low = $probe + 1;
            } elseif ($probe === 0 || $resourcePoint > $points[$probe - 1] || $notfound === true) {
                if ($notfound) {
                    // if not found is true, it means binary search failed to find any point greater
                    // than ressource point, in this case, the last point is the bigest lower
                    // point and first point is the next one after cycle
                    $probe = 0;
                }

                $results[] = $this->pointToNode[$points[$probe]];

                if ($getCount > 1) {
                    for ($i = $getCount - 1; $i > 0; --$i) {
                        if (++$probe > $this->pointCount - 1) {
                            $probe = 0; // cycle
                        }
                        $results[] = $this->pointToNode[$points[$probe]];
                    }
                }

                break;
            } else {
                $high = $probe - 1;
            }
        }

        return array_unique($results);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s{nodes:[%s]}, nodeCount:%d, pointCount:%d, nodeToPoint:%s, pointToNode:%s, sortedPoints:%s',
            get_class($this),
            implode(',', $this->getAllNodes()),
            $this->nodeCount,
            $this->pointCount,
            json_encode($this->nodeToPoint),
            json_encode($this->pointToNode)
        );
    }

    // ----------------------------------------
    // private methods

    /**
     * Sorts the internal mapping (points to nodes) by point.
     */
    private function sortPointNodes()
    {
        // sort by key (point) if not already
        if (!$this->pointToNodeSorted) {
            ksort($this->pointToNode, SORT_REGULAR);
            $this->pointToNodeSorted = true;
            $this->sortedPoints = array_keys($this->pointToNode);
            $this->pointCount = count($this->sortedPoints);
        }
    }
}
