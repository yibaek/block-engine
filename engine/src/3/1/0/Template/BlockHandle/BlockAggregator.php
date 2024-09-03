<?php
namespace Ntuple\Synctree\Template\BlockHandle;

use ArrayIterator;
use Traversable;

class BlockAggregator implements \IteratorAggregate, \Countable
{
    private $blocks;

    /**
     * BlockAggregator constructor.
     * @param IBlock ...$blocks
     */
    public function __construct(IBlock ...$blocks)
    {
        $this->blocks = $blocks;
    }

    /**
     * @return ArrayIterator|Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->blocks);
    }

    /**
     * @param IBlock $blocks
     */
    public function addBlock(IBlock $blocks): void
    {
        $this->blocks[] = $blocks;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->blocks);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }
}