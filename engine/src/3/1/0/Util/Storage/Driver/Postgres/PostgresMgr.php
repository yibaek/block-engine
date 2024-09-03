<?php declare(strict_types=1);

namespace Ntuple\Synctree\Util\Storage\Driver\Postgres;

use Exception;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\Storage\Driver\IRDbMgr;
use Ntuple\Synctree\Util\Storage\Driver\RdbConnectionInfo;
use Ntuple\Synctree\Util\Storage\Driver\RdbSslMode;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;


/**
 * Postgres Connection / Transaction 관리
 *
 * @since SYN-389
 */
class PostgresMgr implements IRDbMgr
{
    public const DRIVER_NAME = 'postgresql';

    /** @var array */
    private $config;

    /** @var LogMessage */
    private $logger;

    /** @var PDO */
    private $connection;

    /** @var bool 트랜잭션 쿼리 실행 중이면 참 */
    private $isTransactionMode;

    /** @var RdbConnectionInfo */
    private $connectionInfo;

    /**
     * @param LogMessage $logger
     * @param array $config required keys: host, port, username, password, dbname
     *                    / optional keys: charset, timezone, options
     */
    public function __construct(LogMessage $logger, array $config)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->isTransactionMode = false;

        $this->connectionInfo = new PostgresConnectionInfo($config);
    }

    /**
     * @return LogMessage
     */
    public function getLogger(): LogMessage
    {
        return $this->logger;
    }

    /**
     * @param string $procedureName
     * @param ParameterManager $parameterMgr
     * @return array|null
     * @throws Exception
     */
    public function executeProcedure(string $procedureName, ParameterManager $parameterMgr): ?array
    {
        try {
            // make connection
            $this->makeConnection();

            // prepare procedure
            $stmt = $this->connection->prepare('CALL ' . $procedureName);

            // bind params
            $this->bindParamsForProcedure($stmt, $parameterMgr);

            // execute
            $stmt->execute();

            // set response data
            // postgres procedure does not support result set
            $resData = [
                'result' => [],
                'output' => $this->getProcedureOutput($stmt)
            ];

            // free statement
            unset($stmt);
            $stmt = null;

            return $resData;
        } catch (PostgresStorageException $ex) {
            throw $ex;
        } catch (PDOException $ex) {
            $this->logger->exception($ex, $procedureName);
            throw new PostgresStorageException($ex->getMessage(), 0, $ex);
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $procedureName);
            throw new PostgresStorageException('Failed to execute procedure', 0, $ex);
        } finally {
            try {
                if (isset($stmt) && $stmt) {
                    unset($stmt);
                    $stmt = null;
                }
            } catch (Throwable $ex) {
                $this->logger->exception($ex, $procedureName);
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
            $this->makeConnection();

            $this->setTransaction();

            $stmt = $this->connection->prepare($queryString);

            $this->bindParamsForQuery($stmt, $parameterMgr->getParameterWithoutOut());

            $stmt->execute();

            $resultRows = $this->getResultRowsForQuery($stmt);

            // free statement
            unset($stmt);
            $stmt = null;

            return $resultRows;
        } catch (PostgresStorageException $ex) {
            throw $ex;
        } catch (PDOException $ex) {
            $this->logger->exception($ex, $queryString);
            throw new PostgresStorageException($ex->getMessage(), 0, $ex);
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $queryString);
            throw new PostgresStorageException('Failed to execute query', 0, $ex);
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
        } catch (PostgresStorageException $ex) {
            throw $ex;
        } catch (PDOException $ex) {
            $this->logger->exception($ex);
            throw new PostgresStorageException($ex->getMessage(), 0, $ex);
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new PostgresStorageException('Failed to execute commit', 0, $ex);
        }
    }

    /**
     * @throws Exception
     */
    public function rollback(): void
    {
        try {
            $this->connection->rollback();
        } catch (PostgresStorageException $ex) {
            throw $ex;
        } catch (PDOException $ex) {
            $this->logger->exception($ex);
            throw new PostgresStorageException($ex->getMessage(), 0, $ex);
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new PostgresStorageException('Failed to execute rollback', 0, $ex);
        }
    }

    public function close(): void
    {
        if (!empty($this->connection)) {
            $this->connection = null;
        }
    }

    /**
     * @param RdbConnectionInfo $conn
     * @return string
     */
    public function makeConnectionString(RdbConnectionInfo $conn): string
    {
        return "pgsql:host={$conn->getHost()};"
            . "port={$conn->getPort()};"
            . "dbname={$conn->getDatabaseName()};"
            . "options='--client_encoding={$conn->getCharset()}';"
            . $this->makeSSLConnectionOptions($conn);
    }

    /**
     * @param RdbConnectionInfo $conn 연결 정보
     * @return string 기본 DSN 뒤에 추가될 SSL 관련 옵션
     * @since SRT-10
     */
    private function makeSSLConnectionOptions(RdbConnectionInfo $conn): string
    {
        if (!$conn->isSSLEnabled()) {
            return '';
        }

        $storagePath = CommonUtil::getUserFileStorePath();
        $sslOptions = $conn->getSSlOptions();

        $clientCertOptions = '';
        if ($sslOptions->hasClientCert()) {
            $certPath = $storagePath . $sslOptions->getClientCertPath();
            $keyPath = $storagePath . $sslOptions->getClientKeyPath();
            $clientCertOptions = "sslcert={$certPath};sslkey={$keyPath};";
        }

        $caPath = $storagePath . $sslOptions->getCACertPath();

        return "sslmode={$conn->getSSLMode()};sslrootcert={$caPath};$clientCertOptions";
    }

    /**
     * @throws PostgresStorageException|Exception
     */
    private function makeConnection(): void
    {
        try {
            $this->tryConnect($this->connectionInfo);

            $this->setDefaultAttribute();
        } catch (PDOException $ex) {
            throw new PostgresStorageException($ex->getMessage(), 0, $ex);
        } catch (Throwable $ex) {
            throw new PostgresStorageException('Failed to connect to postgres server', 0, $ex);
        }
    }

    /**
     * Make connection
     *
     * @param RdbConnectionInfo $connectionInfo
     * @throws Throwable
     */
    private function tryConnect(RdbConnectionInfo $connectionInfo): void
    {
        if (empty($this->connection)) {
            $this->connection = new PDO(
                $this->makeConnectionString($connectionInfo),
                $connectionInfo->getUsername(),
                $connectionInfo->getPassword());
        }
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
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @param PDOStatement $stmt
     * @param Parameter[] $parameters
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
        /** @var Parameter $parameter */
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
    private function getProcedureOutput(PDOStatement $stmt): array
    {
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result !== [] && $result !== false ? $result[0] : [];
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