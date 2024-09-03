<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Oracle;

use ArrayIterator;
use Traversable;

class ParameterManager implements \IteratorAggregate, \Countable
{
    private $parameters;
    private $cursorParameters;

    /**
     * ParameterManager constructor.
     */
    public function __construct()
    {
        $this->parameters = [];
        $this->cursorParameters = [];
    }

    /**
     * @return ArrayIterator|Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->getAllParameters());
    }

    /**
     * @param Parameter $parameter
     */
    public function addParameter(Parameter $parameter): void
    {
        if ($parameter->isCursor()) {
            $this->cursorParameters[] = $parameter;
        } else {
            $this->parameters[] = $parameter;
        }
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

    /**
     * @return array
     */
    public function getBindNames(): array
    {
        $names = [];
        foreach ($this->getIterator() as $parameter) {
            $names[] = $parameter->getBindName();
        }

        return $names;
    }

    /**
     * @param string $delimiter
     * @return string
     */
    public function getBindNamesWithDelimiter(string $delimiter = ','): string
    {
        return implode($delimiter, $this->getBindNames());
    }

    /**
     * @return array
     */
    public function getOutParameter(): array
    {
        $parameters = [];
        foreach ($this->getIterator() as $parameter) {
            if (true === $parameter->isOutParameter()) {
                $parameters[] = $parameter;
            }
        }

        return $parameters;
    }

    /**
     * @return array
     */
    public function getAllParameters(): array
    {
        return array_merge($this->parameters, $this->cursorParameters);
    }

    /**
     * @return array
     */
    public function getCursorParameter(): array
    {
        return $this->cursorParameters;
    }

    /**
     * @return array
     */
    public function getParameterWithoutCursor(): array
    {
        return $this->parameters;
    }
}