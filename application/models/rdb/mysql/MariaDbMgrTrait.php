<?php
namespace models\rdb\mysql;

use PDO;
use PDOStatement;
use Throwable;
use Exception;
use RedisClusterException;
use RedisException;

use libraries\constant\CommonConst;
use libraries\log\LogMessage;
use libraries\util\CommonUtil;
use libraries\util\RedisUtil;
use models\rdb\mysql\query\Delete;
use models\rdb\mysql\query\Insert;
use models\rdb\mysql\query\Update;
use models\rdb\mysql\query\Select;
use models\rdb\mysql\query\OperatorNFunc;
use models\rdb\query\IQuery;
use models\rdb\query\Select as SelectCommon;
use models\rdb\query\Insert as InsertCommon;
use models\rdb\query\Update as UpdateCommon;
use models\rdb\query\Delete as DeleteCommon;
use models\rdb\query\OperatorNFunc as OperatorNFuncCommon;
use models\rdb\query\parameter\ParameterManager;
use models\redis\RedisMgr;

trait MariaDbMgrTrait
{
    /** @var RedisMgr $redisMgr */
    private $redisMgr;

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

            // fetch rows from cache
            if (($rows=$this->fetchRowsFromCache($queryBuilder)) !== false) {
                return $rows;
            }

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
            $resData = $this->executeQuery($this->getSelect()->putQuery('SELECT IF(EXISTS('.$queryBuilder->getQuery().'), 1, 0) AS exist'), !empty($params) ?$params :$queryBuilder->getValues());
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
            $resData = $this->executeQuery($this->getSelect()->putQuery('SELECT LAST_INSERT_ID() AS '.$alias));
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

    public function close(): void
    {
        $this->connection = null;
    }

     /**
     * @return bool
     */
    protected function isConnected(): bool
    {
        if (!empty($this->connection)) {
            return true;
        }

        return false;
    }

    /**
     * @param PDOStatement $stmt
     * @param array|ParameterManager $params
     * @return array
     */
    protected function bindParams(PDOStatement $stmt, $params): array
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
     * @throws Throwable
     */
    protected function fetchRows(PDOStatement $stmt, IQuery $queryBuilder)
    {
        if ($queryBuilder->getType() === 'SELECT') {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->saveRowsToCache($queryBuilder, $rows);
            return $rows;
        }

        return $stmt->rowCount();
    }

    /**
     * @param string $dbname
     * @return array
     */
    protected function getConfig(string $dbname): array
    {
        $credential = CommonUtil::getCredentialConfig('rdb');

        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $config = $config['settings']['rdb'][$dbname];

        return array_merge($credential, $config);
    }

    /**
     * @param IQuery $queryBuilder
     * @return bool|mixed|string
     * @throws Throwable
     */
    public function fetchRowsFromCache(IQuery $queryBuilder)
    {
        try {
            if (!$this->checkQueryCacheStatus($queryBuilder)) {
                return false;
            }

            return RedisUtil::getData($this->getRedisMgr(), $queryBuilder->getCacheKeyWithRawQuery($this->getQueryCacheDebugSessionKey()), $this->getQueryCacheSessionDb());
        } catch (RedisException | RedisClusterException $ex) { 
            LogMessage::exception($ex);
            // redis exception 발생 시 query-cache 기능을 off 한다
            $this->queryCacheForceDisable();
            return false;
        } catch (Exception $ex) {
            LogMessage::exception($ex);
            return false;
        }
    }

    /**
     * @param IQuery $queryBuilder
     * @param $rows
     * @return bool
     * @throws Throwable
     */
    public function saveRowsToCache(IQuery $queryBuilder, $rows): bool
    {
        try {
            if ($rows === [] || $rows === false) {
                return false;
            }
            if (!$this->checkQueryCacheStatus($queryBuilder)) {
                return false;
            }

            return RedisUtil::setDataWithExpire(
                $this->getRedisMgr(),
                $queryBuilder->getCacheKeyWithRawQuery($this->getQueryCacheDebugSessionKey()),
                $this->getQueryCacheSessionDb(),
                $this->getQueryCacheSessionTime(),
                $rows);
        } catch (RedisException | RedisClusterException $ex) { 
            LogMessage::exception($ex);
            // redis exception 발생 시 query-cache 기능을 off 한다
            $this->queryCacheForceDisable();
            return false;
        } catch (Exception $ex) {
            LogMessage::exception($ex);
            return false;
        }
    }

    /**
     * @return RedisMgr
     */
    private function getRedisMgr(): RedisMgr
    {
        if (!isset($this->redisMgr)) {
            $this->redisMgr = new RedisMgr();
        }

        return $this->redisMgr;
    }

    /**
     * @param IQuery $queryBuilder
     * @return bool
     */
    private function checkQueryCacheStatus(IQuery $queryBuilder): bool
    {
        if (
            $this->isQueryCache() && !$this->exceptionDb() && $queryBuilder->getType() === 'SELECT' && $this->isCacheableSql($queryBuilder)) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    private function isQueryCache(): bool
    {
        if (defined('PLAN_MODE') && defined('PLAN_MODE_TESTING') && PLAN_MODE === PLAN_MODE_TESTING) {
            return false;
        }

        if (defined('QUERY_CACHE_DISABLE') && QUERY_CACHE_DISABLE === true) {
            return false;
        }

        return ($this->config['query-cache'] ?? 'false') === 'true';
    }

    /**
     * @return bool
     */
    private function exceptionDb(): bool
    {
        $queryCacheExceptionDbs = $this->config['query-cache.exception-dbs'] ?? '';
        if ($queryCacheExceptionDbs !== '') {
            $exceptionDbs = explode(',', $queryCacheExceptionDbs);
            if ($exceptionDbs) {
                return in_array($this->config['dbname'], $exceptionDbs);
            }
        }

        return false;
    }

    /**
     * 캐시 가능한 SQL 인지 확인
     * [제외]
     *   - exist() : SELECT IF(EXISTS
     *   - getLastInsertID() : SELECT LAST_INSERT_ID() 
     *   - WHERE 절이 존재하지 않는 쿼리
     * @param IQuery $queryBuilder
     * @return bool ; true : 캐시 / false : 캐시 안함
     */
    private function isCacheableSql(IQuery $queryBuilder): bool
    {
        $sql = $queryBuilder->getRawQuery();
        if (
            $sql === '' ||
            stripos($sql, 'SELECT IF(EXISTS') === 0 || 
            stripos($sql, 'SELECT LAST_INSERT_ID()') === 0 ||
            stripos($sql, ' WHERE ') === false
        ) {
            return false;
        }

        return true;
    }

    /**
     * @return int
     */
    private function getQueryCacheSessionTime(): int
    {
        $queryCacheSessionTime = $this->config['query-cache.session-time'] ?? '';
        if ($queryCacheSessionTime !== '') {
            return (int) $queryCacheSessionTime;
        }

        return CommonConst::REDIS_SESSION_EXPIRE_TIME_MIN_3;
    }

    /**
     * @return int
     */
    private function getQueryCacheSessionDb(): int
    {
        $queryCacheSessionDb = $this->config['query-cache.session-db'] ?? '';
        if ($queryCacheSessionDb !== '') {
            return (int) $queryCacheSessionDb;
        }

        return CommonConst::REDIS_SESSION;
    }

    /**
     * @return bool
     */
    private function getQueryCacheDebugSessionKey(): bool
    {
        return ($this->config['query-cache.debug-session-key'] ?? 'false') === 'true';
    }

    /**
     * 쿼리 캐시 off 처리
     * @return void
     */
    private function queryCacheForceDisable(): void
    {
        if (!defined('QUERY_CACHE_DISABLE')) {
            define('QUERY_CACHE_DISABLE', true);
        }
    }
}