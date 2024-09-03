<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Oracle\Oci8;

use Throwable;

class Oci8Statement extends Oci8ErrorHandler
{
    private $statement;
    private $connection;
    private $options;

    /**
     * Oci8Statement constructor.
     * @param Oci8Connect $connection
     * @param $statement
     * @param array $options
     */
    public function __construct(Oci8Connect $connection, $statement, array $options = [])
    {
        $this->connection = $connection;
        $this->statement = $statement;
        $this->options = $options;
    }

    /**
     * @param string $bindName
     * @param $value
     * @param int $length
     * @param int $type
     * @return bool
     * @throw Oci8Exception
     */
    public function bindParam(string $bindName, &$value, int $length = -1, int $type = SQLT_CHR): bool
    {
        try {
            $result = oci_bind_by_name($this->statement, $bindName, $value, $length, $type);
            if ($result !== true) {
                $e = oci_error($this->statement);
                throw new Oci8Exception($e['message'], $e['code']);
            }

            return $result;
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_bind_by_name/']);
            throw new Oci8Exception('Failed to bind param');
        }
    }

    /**
     * @return bool
     * @throw Oci8Exception
     */
    public function execute(): bool
    {
        try {
            $result = oci_execute($this->statement, $this->isAutoCommit() ?OCI_COMMIT_ON_SUCCESS :OCI_NO_AUTO_COMMIT);
            if ($result !== true) {
                $e = oci_error($this->statement);
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
            while (($row=oci_fetch_assoc($this->statement)) !== false) {
                $resultRows[] = $row;
            }

            return $resultRows;
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_fetch_assoc/']);
            throw new Oci8Exception('Failed to fetch all');
        }
    }

    /**
     * @return int
     */
    public function getNumRows(): int
    {
        try {
            $result = oci_num_rows($this->statement);
            if ($result === false) {
                $e = oci_error($this->statement);
                throw new Oci8Exception($e['message'], $e['code']);
            }

            return $result;
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_num_rows/']);
            throw new Oci8Exception('Failed to get num rows');
        }
    }

    /**
     * @return string
     */
    public function getStatementType(): string
    {
        try {
            return oci_statement_type($this->statement);
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_statement_type/']);
            throw new Oci8Exception('Failed to get statement type');
        }
    }

    public function close(): void
    {
        try {
            if ($this->statement) {
                oci_free_statement($this->statement);
                $this->statement = null;
            }
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_free_statement/']);
            throw new Oci8Exception('Failed to close statement');
        }
    }

    /**
     * @return bool
     */
    private function isAutoCommit(): bool
    {
        return !isset($this->options['auto_commit']) || $this->options['auto_commit'] === true;
    }
}