<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Mysql;

use Exception;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Util\Storage\Driver\IRDbMgr;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

class MysqlMgr implements IRDbMgr
{
    private $config;
    private $logger;
    private $connection;
    private $isTransactionMode;

    /**
     * MysqlMgr constructor.
     * @param LogMessage $logger
     * @param array $config
     */
    public function __construct(LogMessage $logger, array $config)
    {
        $this->config = $this->getConfig($config);
        $this->logger = $logger;
        $this->isTransactionMode = false;
    }

    /**
     * @return LogMessage
     */
    public function getLogger(): LogMessage
    {
        return $this->logger;
    }

    /**
     * @param string $procedureString
     * @param ParameterManager $parameterMgr
     * @return array|null
     * @throws Exception
     */
    public function executeProcedure(string $procedureString, ParameterManager $parameterMgr): ?array
    {
        try {
            // make connection
            $this->makeConnection();

            // prepare procedure
            $stmt = $this->connection->prepare('CALL ' . $procedureString);

            // bind params
            $this->bindParamsForProcedure($stmt, $parameterMgr);

            // execute
            $stmt->execute();

            // set response data
            $resData = [
                'result' => $this->getResultRowsForProcedure($stmt),
                'output' => $this->getOutput($parameterMgr->getOutParameter())
            ];

            // free statement
            unset($stmt);
            $stmt = null;

            return $resData;
        } catch (MysqlStorageException $ex) {
            throw $ex;
        } catch (PDOException $ex) {
            $this->logger->exception($ex, $procedureString);
            throw new MysqlStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $procedureString);
            throw new MysqlStorageException('Failed to execute procedure');
        } finally {
            try {
                if (isset($stmt) && $stmt) {
                    unset($stmt);
                    $stmt = null;
                }
            } catch (Throwable $ex) {
                $this->logger->exception($ex, $procedureString);
            }
        }
    }

    /**
     * @param string $queryString
     * @param ParameterManager $parameterMgr
     * @return array|int
     * @throws Exception
     */
    public function executeQuery(string $queryString, ParameterManager $parameterMgr)
    {
        try {
            // make connection
            $this->makeConnection();

            // set transaction
            $this->setTransaction();

            // prepare query
            $stmt = $this->connection->prepare($queryString);

            // bind params
            $this->bindParamsForQuery($stmt, $parameterMgr->getParameterWithoutOut());

            // execute
            $stmt->execute();

            // fetch all
            $resultRows = $this->getResultRowsForQuery($stmt);

            // free statement
            unset($stmt);
            $stmt = null;

            return $resultRows;
        } catch (MysqlStorageException $ex) {
            throw $ex;
        } catch (PDOException $ex) {
            $this->logger->exception($ex, $queryString);
            throw new MysqlStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $queryString);
            throw new MysqlStorageException('Failed to execute query');
        } finally {
            try {
                if (isset($stmt) && $stmt) {
                    unset($stmt);
                    $stmt = null;
                }
            } catch (Throwable $ex) {
                $this->logger->exception($ex, $queryString);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function commit(): void
    {
        try {
            $this->connection->commit();
        } catch (MysqlStorageException $ex) {
            throw $ex;
        } catch (PDOException $ex) {
            $this->logger->exception($ex);
            throw new MysqlStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new MysqlStorageException('Failed to execute commit');
        }
    }

    /**
     * @throws Exception
     */
    public function rollback(): void
    {
        try {
            $this->connection->rollback();
        } catch (MysqlStorageException $ex) {
            throw $ex;
        } catch (PDOException $ex) {
            $this->logger->exception($ex);
            throw new MysqlStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new MysqlStorageException('Failed to execute rollback');
        }
    }

    public function close(): void
    {
        if (!empty($this->connection)) {
            $this->connection = null;
        }
    }

    /**
     * @throws MysqlStorageException|Exception
     */
    private function makeConnection(): void
    {
        try {
            // make connection
            $this->tryConnect($this->config);

            // set default attribute
            $this->setDefaultAttribute();
        } catch (PDOException $ex) {
            $this->logger->exception($ex);
            throw new MysqlStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new MysqlStorageException('Failed to connect');
        }
    }

    /**
     * @param array $config
     * @throws Throwable
     */
    private function tryConnect(array $config): void
    {
        // make connection
        if (empty($this->connection)) {
            $this->connection = new PDO($this->makeConnectingString($config), $config['username'], $config['password']);
        }
    }

    /**
     * @param $config
     * @return string
     */
    private function makeConnectingString(array $config): string
    {
        return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['dbname'], $config['charset']);
    }

    /**
     * @param int $attribute
     * @param int $value
     * @return void
     */
    private function setAttribute(int $attribute, int $value): void
    {
        $this->connection->setAttribute($attribute, $value);
    }

    private function setDefaultAttribute(): void
    {
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @param array $connectionInfo
     * @return array
     */
    private function getConfig(array $connectionInfo): array
    {
        return $connectionInfo;
    }

    /**
     * @param PDOStatement $stmt
     * @param array $parameters
     */
    private function bindParamsForQuery(PDOStatement $stmt, array $parameters): void
    {
        foreach ($parameters as $parameter) {
            $stmt->bindParam($parameter->getBindName(), $parameter->value, $parameter->getType(), $parameter->getLength());
        }
    }

    /**
     * @param PDOStatement $stmt
     * @param ParameterManager $parameterMgr
     */
    private function bindParamsForProcedure(PDOStatement $stmt, ParameterManager $parameterMgr): void
    {
        foreach ($parameterMgr as $parameter) {
            $stmt->bindParam($parameter->getIndex(), $parameter->value, $parameter->getType(), $parameter->getLength());
        }
    }

    /**
     * @param array $outputParams
     * @return array
     */
    private function getOutput(array $outputParams): array
    {
        if (empty($outputParams)) {
            return [];
        }

        $params = [];
        foreach ($outputParams as $parameter) {
            $params[] = $parameter->getBindName().' AS '.$parameter->getName();
        }

        // make query
        $query = 'select ' . implode(', ', $params);

        // get output
        $row = $this->connection->query($query)->fetchAll(PDO::FETCH_ASSOC);
        return $row[0];
    }

    /**
     * @param PDOStatement $stmt
     * @return array
     */
    private function getResultRowsForProcedure(PDOStatement $stmt): array
    {
        $resultRows = [];
        do {
            $resultRows[] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } while ($stmt->nextRowset());

        return $resultRows;
    }

    /**
     * @param PDOStatement $stmt
     * @return array|int
     */
    private function getResultRowsForQuery(PDOStatement $stmt)
    {
        if ($stmt->columnCount() === 0) {
            return $stmt->rowCount();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function setTransaction(): void
    {
        if ($this->isTransactionMode === true || !isset($this->config['options']['auto_commit']) || $this->config['options']['auto_commit'] === true) {
            return;
        }

        $this->connection->beginTransaction();
        $this->isTransactionMode = true;
    }
}