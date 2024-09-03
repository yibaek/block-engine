<?php
namespace models\rdb\oracle;

use RedisClusterException;
use RedisException;
use PDO;
use PDOStatement;
use Throwable;
use JsonException;

use libraries\log\LogMessage;
use libraries\util\RedisUtil;
use libraries\util\CommonUtil;
use libraries\constant\CommonConst;
use models\redis\RedisMgr;
use models\redis\RedisKeys;
use models\rdb\query\Delete as DeleteCommon;
use models\rdb\query\Insert as InsertCommon;
use models\rdb\query\Select as SelectCommon;
use models\rdb\query\Update as UpdateCommon;
use models\rdb\query\OperatorNFunc as OperatorNFuncCommon;
use models\rdb\query\parameter\ParameterManager;
use models\rdb\IRDbHandler;
use models\rdb\IRdbMgr;
use models\rdb\query\IQuery;
use models\rdb\oracle\query\Delete;
use models\rdb\oracle\query\Insert;
use models\rdb\oracle\query\Select;
use models\rdb\oracle\query\Update;
use models\rdb\oracle\query\OperatorNFunc;

class OracleShardMgr implements IRdbMgr
{
    private $redis;
    private $centerDb;
    private $config;
    private $connection;
    private $handler;

    /**
     * OracleShardMrg constructor.
     * @param RedisMgr $redis
     * @param IRdbMgr $centerDb
     * @param array|null $config
     * @param string $dbname
     */
    public function __construct(RedisMgr $redis, IRdbMgr $centerDb, array $config = null, string $dbname = '')
    {
        $this->redis = $redis;
        $this->centerDb = $centerDb;

        // set config
        if (empty($config)) {
            $this->config = $this->getConfig($dbname);
        } else {
            $this->config = $config;
        }

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
     * @param int $attribute
     * @param int $value
     * @return void
     */
    public function setAttribute(int $attribute, int $value): void
    {
        $this->connection->setAttribute($attribute, $value);
    }

    /**
     * @param IQuery $queryBuilder
     * @param array|ParameterManager|null $params
     * @return array|int
     * @throws Throwable
     */
    public function executeQuery(IQuery $queryBuilder, $params = null)
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
            LogMessage::exception($ex);
            throw $ex;
        } finally {
            // logging
            if (!empty($queryType) && !empty($query)) {
                LogMessage::query($queryType, $query, $bindings);
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
            $resData = $this->executeQuery($this->getSelect()->putQuery('SELECT CASE WHEN EXISTS('.$queryBuilder->getQuery().') THEN 1 ELSE 0 END AS exist FROM DUAL'), !empty($params) ?$params :$queryBuilder->getValues());
            return (int)$resData[0]['exist'] === 1;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
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
            $resData = $this->executeQuery($this->getSelect()->putQuery('select '.$sequenceID.'.currval AS '.$alias.' from dual'));
            return (int)$resData[0][$alias];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    public function getSelect(): SelectCommon
    {
        return new Select();
    }

    public function getDelete(): DeleteCommon
    {
        return new Delete();
    }

    public function getUpdate(): UpdateCommon
    {
        return new Update();
    }

    public function getInsert(): InsertCommon
    {
        return new Insert();
    }

    public function getOperatorNFunc(): OperatorNFuncCommon
    {
        return new OperatorNFunc();
    }

    /**
     * @param int|null $slaveID
     * @throws Throwable
     */
    public function makeConnection(int $slaveID = null): void
    {
        // make connection
        $this->tryConnect($slaveID);

        // set default attribute
        $this->setDefaultAttribute();
    }

    public function close(): void
    {
        $this->connection = null;
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
     * @param int $slaveID
     * @throws Throwable
     * @throws JsonException
     */
    private function tryConnect(int $slaveID): void
    {
        if (false === $this->isConnected()) {
            $shardKey = RedisKeys::makeLogDbShardKey($slaveID);
            try {
                $connectionInfo = RedisUtil::getData($this->redis, $shardKey, CommonConst::REDIS_SESSION);
            } catch (RedisException | RedisClusterException $ex) {
                LogMessage::exception($ex);
                // redis connection failed - do nothing
            } 

            // get shard connection info and set to redis
            if (empty($connectionInfo)) {
                $connectionInfo = $this->getShardConnectionInfo($slaveID);

                try {
                    RedisUtil::setDataWithExpire(
                        $this->redis,
                        $shardKey,
                        CommonConst::REDIS_SESSION,
                        CommonConst::REDIS_SESSION_EXPIRE_TIME_DAY_7,
                        json_encode($connectionInfo, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                } catch (RedisException | RedisClusterException $ex) {
                    LogMessage::exception($ex);
                    // redis connection failed - do nothing
                } 

            }

            // make connection
            $this->connection = new PDO($this->makeConnectingString($connectionInfo), $connectionInfo['username'], $connectionInfo['password']);
        }
    }

    /**
     * @param $config
     * @return string
     */
    private function makeConnectingString(array $config): string
    {
        return sprintf('oci:dbname=//%s:%s/%s;charset=%s', $config['host'], $config['port'], $config['dbname'], $config['charset']);
    }

    /**
     * @param PDOStatement $stmt
     * @param array|ParameterManager $params
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
     * @param int $slaveID
     * @return array
     * @throws Throwable
     */
    private function getShardConnectionInfo(int $slaveID): array
    {
        $shardInfoString = $this->centerDb->getHandler()->executeGetShardInfo($slaveID);
        $shardInfo = explode(',', $shardInfoString);

        // set host, port
        $this->config['host'] = $shardInfo[0];
        $this->config['port'] = $shardInfo[1];

        return $this->config;
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
}