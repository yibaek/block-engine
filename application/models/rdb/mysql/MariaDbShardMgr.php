<?php
namespace models\rdb\mysql;

use RedisException;
use RedisClusterException;
use PDO;
use Throwable;
use JsonException;

use models\rdb\IRdbMgr;
use models\redis\RedisMgr;
use models\redis\RedisKeys;
use models\rdb\IRDbHandler;
use libraries\util\RedisUtil;
use libraries\constant\CommonConst;
use libraries\log\LogMessage;

class MariaDbShardMgr implements IRdbMgr
{
    private $redis;
    private $centerDb;
    private $config;
    private $connection;
    private $handler;

    use MariaDbMgrTrait;

    /**
     * MariaDbShardMgr constructor.
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

    /**
     * @param int $slaveID
     * @throws Throwable
     * @throws JsonException
     */
    private function tryConnect(int $slaveID): void
    {
        if (false === $this->isConnected()) {
            $shardKey = RedisKeys::makeLogDbShardKey($slaveID);
            $connectionInfo = null;
            try {
                $connectionInfo = RedisUtil::getData($this->redis, $shardKey, CommonConst::REDIS_SESSION, false);
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
                        json_encode($connectionInfo, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        false
                    );
                } catch (RedisException | RedisClusterException $e) {
                    LogMessage::exception($ex);
                    // redis connection failed - do nothing
                }
            }

            // make connection
            $this->connection = new PDO(
                $this->makeConnectingString($connectionInfo),
                $connectionInfo['username'], $connectionInfo['password'],
                $this->getTLSOptions($connectionInfo)
            );
        }
    }

    /**
     * @param array $config
     * @return string
     */
    private function makeConnectingString(array $config): string
    {
        return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['dbname'], $config['charset']);
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

        // set host, port and username
        // @see https://redmine.nntuple.com/issues/6347
        foreach (['host', 'port', 'username'] as $index => $configKey) {
            if (isset($shardInfo[$index]) && (string) $shardInfo[$index] !== '') {
                $this->config[$configKey] = $shardInfo[$index];
            }
        }

        return $this->config;
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
