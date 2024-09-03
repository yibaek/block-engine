<?php
namespace Ntuple\Synctree\Template\Operator\Unit;

use ArrayIterator;
use Ntuple\Synctree\Template\Operator\IOperatorUnit;
use Traversable;

class OperatorUnitAggregator implements \IteratorAggregate
{
    private $units;

    /**
     * OperatorUnitAggregator constructor.
     * @param IOperatorUnit ...$operatorUnits
     */
    public function __construct(IOperatorUnit ...$operatorUnits)
    {
        $this->units = $operatorUnits;
    }

    /**
     * @return ArrayIterator|Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->units);
    }

    /**
     * @param IOperatorUnit $operatorUnit
     */
    public function addOperatorUnit(IOperatorUnit $operatorUnit): void
    {
        $this->units[] = $operatorUnit;
    }
}