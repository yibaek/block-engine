<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Oracle;

use Exception;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Util\Storage\Driver\IRDbMgr;
use Ntuple\Synctree\Util\Storage\Driver\Oracle\Oci8\Oci8Connect;
use Ntuple\Synctree\Util\Storage\Driver\Oracle\Oci8\Oci8Exception;
use Ntuple\Synctree\Util\Storage\Driver\Oracle\Oci8\Oci8Statement;
use Throwable;

class OracleMgr implements IRDbMgr
{
    private $config;
    private $logger;
    private $connection;

    /**
     * @var bool
     * @since SRT-173
     */
    private $isPconnectDisabled;

    /**
     * OracleMgr constructor.
     * @param LogMessage $logger
     * @param array $config
     * @param bool $isPconnectDisabled @since SRT-173 pconnect 동작을 방지하는 플래그.
     */
    public function __construct(LogMessage $logger, array $config, bool $isPconnectDisabled = false)
    {
        $this->config = $this->getConfig($config);
        $this->logger = $logger;
        $this->isPconnectDisabled = $isPconnectDisabled;
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
     * @return null[]|null
     * @throws OracleStorageException|Exception
     */
    public function executeProcedure(string $procedureString, ParameterManager $parameterMgr): ?array
    {
        try {
            // make connection
            $this->makeConnection();

            // prepare procedure
            $stmt = $this->connection->prepare('BEGIN ' . $procedureString . ' END;');

            // bind params
            $this->bindParams($stmt, $parameterMgr->getParameterWithoutCursor());

            // bind cursor params
            $cursors = $this->bindCursorParams($stmt, $parameterMgr->getCursorParameter());

            // execute
            $stmt->execute();

            // free statement
            $stmt->close();

            return [
                'result' => $this->getResultRowsForProcedure($cursors),
                'output' => $this->getOutput($parameterMgr)
            ];
        } catch (OracleStorageException $ex) {
            throw $ex;
        } catch (Oci8Exception $ex) {
            $this->logger->exception($ex, $procedureString);
            throw (new OracleStorageException($ex->getMessage()))->setData($ex->getData());
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $procedureString);
            throw new OracleStorageException('Failed to execute procedure');
        } finally {
            try {
                if (isset($stmt) && $stmt) {
                    $stmt->close();
                }
                if (isset($cursors) && $cursors) {
                    foreach ($cursors as $cursor) {
                        $cursor->close();
                    }
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

            // prepare procedure
            $stmt = $this->connection->prepare($queryString);

            // bind params
            $this->bindParams($stmt, $parameterMgr->getParameterWithoutCursor());

            // execute
            $stmt->execute();

            // fetch all
            $resultRows = $this->getResultRowsForQuery($stmt);

            // free statement
            $stmt->close();

            return $resultRows;
        } catch (OracleStorageException $ex) {
            throw $ex;
        } catch (Oci8Exception $ex) {
            $this->logger->exception($ex, $queryString);
            throw (new OracleStorageException($ex->getMessage()))->setData($ex->getData());
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $queryString);
            throw new OracleStorageException('Failed to execute query');
        } finally {
            try {
                if (isset($stmt) && $stmt) {
                    $stmt->close();
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
        } catch (OracleStorageException $ex) {
            throw $ex;
        } catch (Oci8Exception $ex) {
            $this->logger->exception($ex);
            throw (new OracleStorageException($ex->getMessage()))->setData($ex->getData());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new OracleStorageException('Failed to execute commit');
        }
    }

    /**
     * @throws Exception
     */
    public function rollback(): void
    {
        try {
            $this->connection->rollback();
        } catch (OracleStorageException $ex) {
            throw $ex;
        } catch (Oci8Exception $ex) {
            $this->logger->exception($ex);
            throw (new OracleStorageException($ex->getMessage()))->setData($ex->getData());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new OracleStorageException('Failed to execute rollback');
        }
    }

    /**
     * @throws Exception
     */
    public function close(): void
    {
        try {
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
        }
    }

    /**
     * @throws Exception
     */
    private function makeConnection(): void
    {
        try {
            // make connection
            $this->tryConnect($this->config);
        } catch (Oci8Exception $ex) {
            $this->logger->exception($ex);
            throw (new OracleStorageException($ex->getMessage()))->setData($ex->getData());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new OracleStorageException('Failed to connect');
        }
    }

    /**
     * @param array $config
     */
    private function tryConnect(array $config): void
    {
        // make connection
        if (empty($this->connection)) {
            $this->connection = new Oci8Connect(
                $this->makeConnectingString($config),
                $config['username'], $config['password'], $config['charset'], $config['options'] ?? [],
                $this->isPconnectDisabled);
        }
    }

    /**
     * @param array $config
     * @return string
     */
    private function makeConnectingString(array $config): string
    {
        $options = $config['options'] ?? [];
        if (isset($options['ez_connect']) && $options['ez_connect'] === false) {
            if (!isset($config['tns']) || empty($config['tns'])) {
                throw new OracleStorageException('Failed to get connection string');
            }

            return $config['tns'];
        }

        return sprintf('%s:%s/%s', $config['host'], $config['port'], $config['sid']);
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
     * @param Oci8Statement $stmt
     * @param array $parameters
     */
    private function bindParams(Oci8Statement $stmt, array $parameters): void
    {
        foreach ($parameters as $parameter) {
            $stmt->bindParam($parameter->getBindName(), $parameter->value, $parameter->getLength(), $parameter->getType());
        }
    }

    /**
     * @param Oci8Statement $stmt
     * @param array $parameters
     * @return array
     */
    private function bindCursorParams(Oci8Statement $stmt, array $parameters): array
    {
        $cursors = [];
        foreach ($parameters as $parameter) {
            $cursor = $this->connection->getNewCursor();
            $stmt->bindParam($parameter->getBindName(), $cursor->resource, $parameter->getLength(), $parameter->getType());
            $cursor->setParameter($parameter);
            $cursors[] = $cursor;
        }

        return $cursors;
    }

    /**
     * @param ParameterManager $parameterMgr
     * @return array
     */
    private function getOutput(ParameterManager $parameterMgr): array
    {
        if (true === $parameterMgr->isEmpty()) {
            return [];
        }

        $resData = [];
        foreach ($parameterMgr->getOutParameter() as $parameter) {
            $resData[$parameter->getName()] = $parameter->getValue();
        }

        return $resData;
    }

    /**
     * @param array $cursors
     * @return array
     */
    private function getResultRowsForProcedure(array $cursors): array
    {
        $resultRows = [];
        foreach ($cursors as $cursor) {
            // execute cursor
            $cursor->execute();

            // fetch cursor
            $resultRows[$cursor->getName()] = $cursor->fetchAll();

            // free cursor
            $cursor->close();
        }

        return $resultRows;
    }

    /**
     * @param Oci8Statement $stmt
     * @return array|int
     */
    private function getResultRowsForQuery(Oci8Statement $stmt)
    {
        if (in_array($stmt->getStatementType(), ['INSERT', 'UPDATE', 'DELETE'])) {
            return $stmt->getNumRows();
        }

        return $stmt->fetchAll();
    }
}