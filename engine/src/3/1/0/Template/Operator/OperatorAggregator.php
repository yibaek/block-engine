<?php
namespace Ntuple\Synctree\Template\Operator;

use ArrayIterator;
use Traversable;

class OperatorAggregator implements \IteratorAggregate
{
    private $operators;

    /**
     * OperatorAggregator constructor.
     * @param Operator ...$operator
     */
    public function __construct(Operator ...$operator)
    {
        $this->operators = $operator;
    }

    /**
     * @return ArrayIterator|Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->operators);
    }

    /**
     * @param Operator $operator
     */
    public function addOperator(Operator $operator): void
    {
        $this->operators[] = $operator;
    }
}