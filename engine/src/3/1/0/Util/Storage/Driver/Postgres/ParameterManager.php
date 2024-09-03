<?php declare(strict_types=1);

namespace Ntuple\Synctree\Util\Storage\Driver\Postgres;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * @since SYN-389
 */
class ParameterManager implements IteratorAggregate, Countable
{
    private $parameters;
    private $outParameters;

    /**
     * ParameterManager constructor.
     */
    public function __construct()
    {
        $this->parameters = [];
        $this->outParameters = [];
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->getAllParameters());
    }

    /**
     * @param Parameter $parameter
     */
    public function addParameter(Parameter $parameter): void
    {
        if ($parameter->isOutParameter()) {
            $this->outParameters[] = $parameter;
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
     * @return string
     */
    public function makeQueryString(): string
    {
        $query = '';

        // add input params string
        if (!empty($this->parameters)) {
            $query .= str_repeat('?,', count($this->parameters));
            $query = substr($query, 0, -1);
        }

        // add output params string
        if (!empty($this->outParameters)) {
            if (!empty($this->parameters)) {
                $query .= ',';
            }

            $query .= str_repeat('?,', count($this->outParameters));
            $query = substr($query, 0, -1);
        }

        return $query;
    }

    /**
     * @return array
     */
    public function getOutParameter(): array
    {
        return $this->outParameters;
    }

    /**
     * @return array
     */
    public function getAllParameters(): array
    {
        return array_merge($this->parameters, $this->outParameters);
    }

    /**
     * @return array
     */
    public function getParameterWithoutOut(): array
    {
        return $this->parameters;
    }
}