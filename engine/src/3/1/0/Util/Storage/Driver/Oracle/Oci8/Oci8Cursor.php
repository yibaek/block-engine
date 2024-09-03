<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Oracle\Oci8;

use Ntuple\Synctree\Util\Storage\Driver\Oracle\Parameter;
use Throwable;

class Oci8Cursor extends Oci8ErrorHandler
{
    private $connection;
    private $parameter;
    public $resource;

    /**
     * Oci8Cursor constructor.
     * @param Oci8Connect $connection
     * @param resource $resource
     */
    public function __construct(Oci8Connect $connection, $resource)
    {
        $this->connection = $connection;
        $this->resource = $resource;
    }

    /**
     * @param Parameter $parameter
     */
    public function setParameter(Parameter $parameter): void
    {
        $this->parameter = $parameter;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->parameter->getName();
    }

    /**
     * @return bool
     * @throw Oci8Exception
     */
    public function execute(): bool
    {
        try {
            $result = oci_execute($this->resource);
            if ($result !== true) {
                $e = oci_error($this->resource);
                throw new Oci8Exception($e['message'], $e['code']);
            }

            return $result;
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_execute/']);
            throw new Oci8Exception('Failed to execute');
        }
    }

    /**
     * @return array
     */
    public function fetchAll(): array
    {
        try {
            $resultRows = [];
            while (($row=oci_fetch_assoc($this->resource)) !== false) {
                $resultRows[] = $row;
            }

            return $resultRows;
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_fetch_assoc/']);
            throw new Oci8Exception('Failed to fetch all');
        }
    }

    public function close(): void
    {
        try {
            if ($this->resource) {
                oci_free_statement($this->resource);
                $this->resource = null;
            }
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_free_statement/']);
            throw new Oci8Exception('Failed to close cursor');
        }
    }
}