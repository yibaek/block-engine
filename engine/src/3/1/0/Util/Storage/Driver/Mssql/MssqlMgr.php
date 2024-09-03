<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver\Mssql;

use Exception;
use Throwable;
use PDO;
use PDOException;
use PDOStatement;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Util\Storage\Driver\IRDbMgr;
use Ntuple\Synctree\Util\Storage\Driver\RdbConnectionInfo;

/**
 * Microsoft SQLServer Connection / Transaction manager
 *
 * @since SRT-140
 */
class MssqlMgr implements IRdbMgr
{
    /** @var string synctree 내부적으로 사용되는 구분자로서, `PDO driver name`과는 별개 */
    public const DRIVER_NAME = 'mssql';

    /** @var array */
    private $config;

    /** @var LogMessage */
    private $logger;

    /** @var PDO */
    private $connection;

    /** @var bool 트랜잭션 쿼리 실행 중이면 참 */
    private $isTransactionMode;

    /** @var MssqlConnectionInfo */
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

        $this->connectionInfo = new MssqlConnectionInfo($config);
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
     * @param array $attributes Attributes for {@link PDO::prepare()}
     * @return array|null
     * @throws Exception
     */
    public function executeProcedure(
        string $procedureName,
        ParameterManager $parameterMgr,
        array $attributes = []): ?array
    {
        try {
            $this->makeConnection();

            $stmt = $this->connection->prepare("{CALL $procedureName}", $attributes);

            $this->bindParamsForProcedure($stmt, $parameterMgr);

            $stmt->execute();

            $resData = [
                'result' => $this->getResultRowsForProcedure($stmt),
                'output' => $this->getProcedureOutput($parameterMgr)
            ];

            unset($stmt);
            $stmt = null;

            return $resData;
        } catch (MssqlStorageException $ex) {
            throw $ex;
        } catch (PDOException $ex) {
            $this->logger->exception($ex, $procedureName);
            throw new MssqlStorageException($ex->getMessage(), 0, $ex);
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $procedureName);
            throw new MssqlStorageException('Failed to execute procedure', 0, $ex);
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
     * @param array $attributes Attributes for {@link PDO::prepare()}
     * @return array|int
     * @throws Exception
     */
    public function executeQuery(
        string $queryString,
        ParameterManager $parameterMgr,
        array $attributes = [])
    {
        try {
            $this->makeConnection();

            $this->setTransaction();

            $stmt = $this->connection->prepare($queryString, $attributes);

            $this->bindParamsForQuery($stmt, $parameterMgr->getInParameters());

            $stmt->execute();

            $resultRows = $this->getResultRowsForQuery($stmt);

            unset($stmt);
            $stmt = null;

            return $resultRows;
        } catch (MssqlStorageException $ex) {
            throw $ex;
        } catch (PDOException $ex) {
            $this->logger->exception($ex, $queryString);
            throw new MssqlStorageException($ex->getMessage(), 0, $ex);
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $queryString);
            throw new MssqlStorageException('Failed to execute query', 0, $ex);
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
        } catch (PDOException $ex) {
            $this->logger->exception($ex);
            throw new MssqlStorageException($ex->getMessage(), 0, $ex);
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new MssqlStorageException('Failed to execute commit', 0, $ex);
        }
    }

    /**
     * @throws Exception
     */
    public function rollback(): void
    {
        try {
            $this->connection->rollBack();
        } catch (PDOException $ex) {
            $this->logger->exception($ex);
            throw new MssqlStorageException($ex->getMessage(), 0, $ex);
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new MssqlStorageException('Failed to execute commit', 0, $ex);
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
     * @return string PDO DSN
     */
    public function makeConnectionString(RdbConnectionInfo $conn): string
    {
        $drvName = MssqlConnectionInfo::DRIVER_NAME;
        return "$drvName:Server={$conn->getHost()},{$conn->getPort()};
            Database={$conn->getDatabaseName()};";
    }

    /**
     * @return void
     */
    private function makeConnection(): void
    {
        try {
            if (null === $this->connection) {
                $this->connection = new PDO(
                    $this->makeConnectionString($this->connectionInfo),
                    $this->connectionInfo->getUsername(),
                    $this->connectionInfo->getPassword());

                $this->setDefaultAttribute();
            }
        } catch (PDOException $ex) {
            throw new MssqlStorageException($ex->getMessage(), 0, $ex);
        } catch (Throwable $ex) {
            throw new MssqlStorageException('Failed to connect to SQLServer', 0, $ex);
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
        $this->setAttribute(PDO::SQLSRV_ATTR_ENCODING, $this->mapCharset());
    }

    /**
     * @return int {@link MssqlCharsetOption} 값을 SQLSRV PDO enum 값으로 변환
     */
    private function mapCharset(): int
    {
        switch ($this->connectionInfo->getCharset()) {
            case MssqlCharsetOption::BINARY:
                return PDO::SQLSRV_ENCODING_BINARY;
            case MssqlCharsetOption::SYSTEM:
                return PDO::SQLSRV_ENCODING_SYSTEM;
            case MssqlCharsetOption::UTF8:
                return PDO::SQLSRV_ENCODING_UTF8;
            default:
                return PDO::SQLSRV_ENCODING_DEFAULT;
        }
    }

    /**
     * @param PDOStatement $stmt
     * @param Parameter[] $parameters
     */
    private function bindParamsForQuery(PDOStatement $stmt, array $parameters): void
    {
        foreach ($parameters as $parameter) {
            $stmt->bindParam(
                $parameter->getBindName(),
                $parameter->value,
                $parameter->getType(),
                $parameter->getLength());
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
            $stmt->bindParam(
                $parameter->getIndex(),
                $parameter->value,
                $parameter->getType(),
                $parameter->getLength());
        }
    }

    /**
     * @param ParameterManager $parameterManager
     * @return array
     */
    private function getProcedureOutput(ParameterManager $parameterManager): array
    {
        $output = [];

        /** @var Parameter $param */
        foreach ($parameterManager->getOutParameters() as $param) {
            $output[$param->getName()] = $param->getValue();
        }

        return $output;
    }

    /**
     * @param PDOStatement $stmt
     * @return array
     */
    private function getResultRowsForProcedure(PDOStatement $stmt): array
    {
        if ($stmt->rowCount() === 0) {
            return [];
        }

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