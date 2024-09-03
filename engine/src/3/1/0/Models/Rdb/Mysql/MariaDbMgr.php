<?php
namespace Ntuple\Synctree\Models\Rdb\Mysql;

use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Models\Rdb\IRDbHandler;
use Ntuple\Synctree\Models\Rdb\IRdbMgr;
use Ntuple\Synctree\Models\Rdb\Mysql\Query\Delete;
use Ntuple\Synctree\Models\Rdb\Mysql\Query\Insert;
use Ntuple\Synctree\Models\Rdb\Mysql\Query\Select;
use Ntuple\Synctree\Models\Rdb\Mysql\Query\Update;
use Ntuple\Synctree\Models\Rdb\Query\IQuery;
use Ntuple\Synctree\Models\Rdb\Query\Delete as DeleteCommon;
use Ntuple\Synctree\Models\Rdb\Query\Insert as InsertCommon;
use Ntuple\Synctree\Models\Rdb\Query\Select as SelectCommon;
use Ntuple\Synctree\Models\Rdb\Query\Update as UpdateCommon;
use Ntuple\Synctree\Util\CommonUtil;
use PDO;
use PDOStatement;
use Throwable;

class MariaDbMgr implements IRdbMgr
{
    private $logger;
    private $config;
    private $connection;
    private $handler;

    /**
     * MariaDbMgr constructor.
     * @param LogMessage $logger
     * @param array|null $config
     * @param string $dbname
     */
    public function __construct(LogMessage $logger, array $config = null, string $dbname = '')
    {
        // set config
        if (empty($config)) {
            $this->config = $this->getConfig($dbname);
        } else {
            $this->config = $config;
        }

        $this->logger = $logger;
        $this->handler = new RDbHandler($this);
    }

    /**
     * @return IRDbHandler
     */
    public function getHandler(): IRDbHandler
    {
        return $this->handler;
    }

    /**
     * @return LogMessage
     */
    public function getLogger(): LogMessage
    {
        return $this->logger;
    }

    /**
     * @param int $attribute
     * @param int $value
     * @return mixed
     */
    public function setAttribute(int $attribute, int $value)
    {
        return $this->connection->setAttribute($attribute, $value);
    }

    /**
     * @param IQuery $queryBuilder
     * @param array $params
     * @return array|int
     * @throws Throwable
     */
    public function executeQuery(IQuery $queryBuilder, array $params = [])
    {
        $queryType = null;
        $query = null;
        $bindings = [];

        try {
            // get query type
            $queryType = $queryBuilder->getType();

            // get query
            $query = $queryBuilder->getQuery();

            // check connection
            if (false === $this->isConnected()) {
                throw new \RuntimeException('connection not established!!');
            }

            // prepare query
            $stmt = $this->connection->prepare($query);

            // bind params
            $bindings = $this->bindParams($stmt, !empty($params) ?$params :$queryBuilder->getValues());

            // execute
            $stmt->execute();

            return $this->fetchRows($stmt, $queryBuilder);
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw $ex;
        } finally {
            // logging
            if (!empty($queryType) && !empty($query)) {
                $this->logger->query($queryType, $query, $bindings);
            }
        }
    }

    /**
     * @param IQuery $queryBuilder
     * @param array $params
     * @return bool
     * @throws Throwable
     */
    public function exist(IQuery $queryBuilder, array $params = []): bool
    {
        try {
            $resData = $this->executeQuery($this->getSelect()->putQuery('SELECT IF(EXISTS('.$queryBuilder->getQuery().'), 1, 0) AS exist'), !empty($params) ?$params :$queryBuilder->getValues());
            return (int)$resData[0]['exist'] === 1;
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $alias
     * @param string|null $sequenceID
     * @return int
     * @throws Throwable
     */
    public function getLastInsertID(string $alias, string $sequenceID = null): int
    {
        try {
            $resData = $this->executeQuery($this->getSelect()->putQuery('SELECT LAST_INSERT_ID() AS '.$alias));
            return (int)$resData[0][$alias];
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw $ex;
        }
    }

    public function makeConnection(): void
    {
        // make connection
        $this->tryConnect($this->config);

        // set default attribute
        $this->setDefaultAttribute();
    }

    /**
     * @return SelectCommon
     */
    public function getSelect(): SelectCommon
    {
        return new Select();
    }

    /**
     * @return DeleteCommon
     */
    public function getDelete(): DeleteCommon
    {
        return new Delete();
    }

    /**
     * @return UpdateCommon
     */
    public function getUpdate(): UpdateCommon
    {
        return new Update();
    }

    /**
     * @return InsertCommon
     */
    public function getInsert(): InsertCommon
    {
        return new Insert();
    }

    /**
     * @return bool
     */
    private function isConnected(): bool
    {
        if (!empty($this->connection)) {
            return true;
        }

        return false;
    }

    /**
     * @param array $config
     */
    private function tryConnect(array $config): void
    {
        if (false === $this->isConnected()) {
            // make connection
            $this->connection = new PDO(
                $this->makeConnectingString($config),
                $config['username'], $config['password'],
                $this->getTLSOptions($config)
            );
        }
    }

    public function close(): void
    {
        if (!empty($this->connection)) {
            $this->connection = null;
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
     * @param PDOStatement $stmt
     * @param $params
     * @return array
     */
    private function bindParams(PDOStatement $stmt, $params): array
    {
        $bindings = [];
        
        if (is_array($params)) {
            foreach ($params as $param) {
                $stmt->bindParam($param[0], $param[1], $param[2]);
                $bindings[$param[0]] = $param[1];
            }
            return $bindings;
        }

        foreach ($params as $param) {
            $stmt->bindParam($param->getBindName(), $param->value, $param->getBindType());
            $bindings[$param->getBindName()] = $param->value;
        }
        return $bindings;
    }

    /**
     * @param PDOStatement $stmt
     * @param IQuery $queryBuilder
     * @return array|int
     */
    private function fetchRows(PDOStatement $stmt, IQuery $queryBuilder)
    {
        if ($queryBuilder->getType() === 'SELECT') {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $stmt->rowCount();
    }

    private function setDefaultAttribute(): void
    {
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
    }

    /**
     * @param string $dbname
     * @return array
     */
    private function getConfig(string $dbname): array
    {
        $credential = CommonUtil::getCredentialConfig('rdb');

        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $config = $config['settings']['rdb'][$dbname];

        return array_merge($credential, $config);
    }

    /**
     * @since SYN-731, SRT-14
     * @param array $config credentials configs
     * @return array Options parameter for PDO
     */
    private function getTLSOptions(array $config): array
    {
        $tlsEnabled = ($config['tls-enable'] ?? false) === 'true';
        return $tlsEnabled ? [
            PDO::MYSQL_ATTR_SSL_CA => $config['tls-ca-cert'] ?? '',
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => ($config['tls-verify-cert'] ?? false) === 'true'
        ] : [];
    }
}