<?php
namespace models\rdb\query\parameter;

use Countable;
use ArrayIterator;
use IteratorAggregate;

class ParameterManager implements IteratorAggregate, Countable
{
    private $index;
    private $parameters;

    /**
     * ParameterManager constructor.
     */
    public function __construct()
    {
        $this->index = 0;
        $this->parameters = [];
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->parameters);
    }

    /**
     * @param Parameter $parameter
     */
    public function addParameter(Parameter $parameter): void
    {
        $this->index++;
        $this->parameters[] = $parameter->setIndex($this->index);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->getIterator());
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }
}