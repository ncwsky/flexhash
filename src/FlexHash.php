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
     * The number of positions to hash each node to.
     * 虚拟节点数,解决节点分布不均的问题
     * @var int
     */
    private $replicas = 64;

    /**
     * The hash algorithm, encapsulated in a FlexHash_Hasher implementation.
     * @var HasherInterface FlexHash_Hasher
     */
    private $hasher;

    /**
     * Internal counter for current number of nodes.
     * @var int 节点数
     */
    public $nodeCount = 0;

    /**
     * Internal map of positions (hash outputs) to nodes.
     * @var array { position => node, ... } 点[hash]到节点的映射
     */
    private $positionToNode = [];

    /**
     * Internal map of nodes to lists of positions that node is hashed to.
     * @var array { node => [ position, position, ... ], ... }
     */
    private $nodeToPosition = [];

    /**
     * Whether the internal map of positions to nodes is already sorted.
     * @var bool
     */
    private $positionToNodeSorted = false;

    /**
     * Sorted positions.
     *
     * @var array
     */
    private $sortedPositions = [];

    /**
     * Internal counter for current number of positions.
     *
     * @var integer
     */
    private $positionCount = 0;

    /**
     * Constructor.
     * @param \FlexHash\Hasher\HasherInterface $hasher
     * @param int $replicas Amount of positions to hash each node to.
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
        if (isset($this->nodeToPosition[$node])) {
            //throw new \Exception("Node '$node' already exists.");
            return $this;
        }

        $this->nodeToPosition[$node] = [];

        // hash the node into multiple positions
        for ($i = 0; $i < round($this->replicas * $weight); ++$i) {
            $position = $this->hasher->hash($node . '#' . $i);
            $this->positionToNode[$position] = $node; // lookup
            $this->nodeToPosition[$node] [] = $position; // node removal
        }

        $this->positionToNodeSorted = false;
        ++$this->nodeCount;

        $this->positionCount = count($this->positionToNode);
        return $this;
    }

    /**
     * Add a list of nodes.
     * @param array $nodes
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
        if (!isset($this->nodeToPosition[$node])) {
            throw new \Exception("Node '$node' does not exist.");
        }

        foreach ($this->nodeToPosition[$node] as $position) {
            unset($this->positionToNode[$position]);
        }

        unset($this->nodeToPosition[$node]);

        $this->positionToNodeSorted = false;
        --$this->nodeCount;

        return $this;
    }

    /**
     * A list of all potential nodes.
     * @return array
     */
    public function getAllNodes(): array
    {
        return array_keys($this->nodeToPosition);
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
    public function getNodes($resource, $getCount = 1): array
    {
        if (!$getCount || $getCount < 0) {
            throw new \Exception('Invalid count requested');
        }

        // handle no nodes
        if ($this->nodeCount == 0) {
            return [];
        }

        // optimize single node
        if ($this->nodeCount == 1) {
            return [current($this->positionToNode)];
        }

        // hash resource to a position
        $resourcePosition = $this->hasher->hash($resource);

        // sort by key (position) if not already 按点排序
        if (!$this->positionToNodeSorted) {
            //ksort($this->positionToNode, SORT_NUMERIC); //SORT_REGULAR
            $this->positionToNodeSorted = true;
            $this->sortedPositions = array_keys($this->positionToNode);
            sort($this->sortedPositions, SORT_NUMERIC);
            $this->positionCount = count($this->sortedPositions);
        }

        $high = $this->positionCount-1;
        $position = 0;
        while($position <= $high){
            $mid = (int)(($position + $high) >> 1); // avoid overflow when computing h
            // $position <= $resourcePosition < $high
            if ($this->sortedPositions[$mid] <= $resourcePosition) {
                $position = $mid + 1;
            } else {
                if($this->sortedPositions[$mid-1]<=$resourcePosition){
                    $position = $mid;
                    break;
                }
                $high = $mid-1;
            }
            //echo $position,'-', $high, '-->',$this->sortedPositions[$mid],PHP_EOL;
        }

        if ($position == $this->positionCount) $position = 0; //超出重置到第一个点

        //var_dump($this->positionCount, $position, $this->sortedPositions[$position], $resourcePosition);

        $node = $this->positionToNode[$this->sortedPositions[$position]];
        $result = [$node=>true];
        if ($getCount > 1) {
            for ($n = 1; $n < $getCount; $n++) {
                if (++$position == $this->positionCount) $position = 0;
                $node = $this->positionToNode[$this->sortedPositions[$position]];
                $result[$node] = true;
            }
        }

        return array_keys($result); //array_unique($result);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s{nodes:[%s]}, nodeCount:%d, positionCount:%d',
            get_class($this),
            implode(',', $this->getAllNodes()),
            $this->nodeCount,
            $this->positionCount
        );
    }
}
