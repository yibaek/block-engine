<?php
namespace models\redis;

use Exception;
use libraries\constant\CommonConst;
use libraries\crypt\AES;
use libraries\log\LogMessage;
use libraries\util\CommonUtil;
use libraries\util\RedisUtil;
use Redis;
use RedisCluster;
use Throwable;

class RedisMgr
{
    protected $config;
    protected $connectionPool;

    /**
     * RedisMgr constructor.
     * @param array|null $config
     */
    public function __construct(array $config = null)
    {
        // load config
        if (empty($config)) {
            $this->config = $this->getConfig();
        } else {
            $this->config = $config;
        }

        // init redis connection pool
        if (false === $this->isCluster()) {
            $this->connectionPool = [];
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @return bool|mixed|string
     * @throws Throwable
     */
    public function getData(int $dbIndex, string $key)
    {
        $key = trim($key);

        try {
            // get connection
            $connection = $this->makeConnection($key, $dbIndex);

            // get data
            if (false !== ($value=$connection->get($key))) {
                // call middleware
                $value = $this->middlewareForAfterGet($dbIndex, $value);
            }

            return $value;
        } catch (Throwable $ex) {
            // loggin exception
            LogMessage::exception($ex, $key);
            throw $ex;
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @param mixed $value
     * @return bool
     * @throws Throwable
     */
    public function setData(int $dbIndex, string $key, $value): bool
    {
        $key = trim($key);

        try {
            // get connection
            $connection = $this->makeConnection($key, $dbIndex);

            // call middleware
            $value = $this->middlewareForBeforeSet($dbIndex, $value);

            // set data
            if (true !== ($isSet=$connection->set($key, $value))) {
                throw new \RuntimeException('failed to set redis data[key:'.$key.']');
            }

            return $isSet;
        } catch (Throwable $ex) {
            // loggin exception
            LogMessage::exception($ex, $key);
            throw $ex;
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @param int $expireTime
     * @param mixed $value
     * @return bool
     * @throws Throwable
     */
    public function setDataWithExpire(int $dbIndex, string $key, int $expireTime, $value): bool
    {
        $key = trim($key);

        try {
            // get connection
            $connection = $this->makeConnection($key, $dbIndex);

            // call middleware
            $value = $this->middlewareForBeforeSet($dbIndex, $value);

            // set data with expire time
            if (true !== ($isSet=$connection->setEx($key, $expireTime, $value))) {
                throw new \RuntimeException('failed to set redis data[key:'.$key.']');
            }

            return $isSet;
        } catch (Throwable $ex) {
            // loggin exception
            LogMessage::exception($ex, $key);
            throw $ex;
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @param mixed $value
     * @return bool
     * @throws Throwable
     */
    public function setDataNotModifyExpire(int $dbIndex, string $key, $value): bool
    {
        // update data with remaining expire time
        return $this->setDataWithExpire($dbIndex, $key, $this->getTtl($dbIndex, $key), $value);
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @return bool
     * @throws Throwable
     */
    public function exist(int $dbIndex, string $key): bool
    {
        $key = trim($key);

        try {
            // get connection
            $connection = $this->makeConnection($key, $dbIndex);

            // check exist key
            return $connection->exists($key);
        } catch (Throwable $ex) {
            // loggin exception
            LogMessage::exception($ex, $key);
            throw $ex;
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @return bool
     * @throws Throwable
     */
    public function del(int $dbIndex, string $key): bool
    {
        $key = trim($key);

        try {
            // get connection
            $connection = $this->makeConnection($key, $dbIndex);

            // delete key
            return 1 === $connection->del($key);
        } catch (Throwable $ex) {
            // loggin exception
            LogMessage::exception($ex, $key);
            throw $ex;
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @param int $expireTime
     * @return bool
     * @throws Throwable
     */
    public function expire(int $dbIndex, string $key, int $expireTime): bool
    {
        $key = trim($key);

        try {
            // get connection
            $connection = $this->makeConnection($key, $dbIndex);

            // update expire time
            return $connection->expire($key, $expireTime);
        } catch (Throwable $ex) {
            // loggin exception
            LogMessage::exception($ex, $key);
            throw $ex;
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @return int
     * @throws Throwable
     */
    public function incr(int $dbIndex, string $key): int
    {
        $key = trim($key);

        try {
            // get connection
            $connection = $this->makeConnection($key, $dbIndex);

            // increment the number
            return $connection->incr($key);
        } catch (Throwable $ex) {
            // loggin exception
            LogMessage::exception($ex, $key);
            throw $ex;
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @return int
     * @throws Throwable
     */
    public function getTtl(int $dbIndex, string $key): int
    {
        $key = trim($key);

        try {
            // get connection
            $connection = $this->makeConnection($key, $dbIndex);

            // get remaining time
            return $connection->ttl($key);
        } catch (Throwable $ex) {
            // loggin exception
            LogMessage::exception($ex, $key);
            throw $ex;
        }
    }

    /**
     * @param string $key
     * @param int $dbIndex
     * @return mixed|Redis|RedisCluster
     * @throws Throwable
     */
    private function makeConnection(string $key, int $dbIndex)
    {
        // check client type is cluster
        if (true === $this->isCluster()) {
            return $this->tryConnect();
        }

        // get index for sharding
        $shard = RedisUtil::getShard($key);
        $index = $shard % count($this->config['host']);

        // get connection
        if (true === array_key_exists($index, $this->connectionPool)) {
            $connection = $this->connectionPool[$index];
        } else {
            $connection = $this->tryConnect($index);
        }

        // select db
        $connection->select($dbIndex);

        return $connection;
    }

    /**
     * @param int|null $index
     * @return Redis|RedisCluster
     * @throws Throwable
     */
    private function tryConnect(int $index = null)
    {
        try {
            if (true === $this->isCluster()) {
                // redis cluster의 seed 정보를 생성
                $seeds = [];
                $count = count($this->config['host']);
                for ($i=0;$i<$count;$i++) {
                    $seeds[] = $this->config['host'][$i].':'.$this->config['port'][$i];
                }

                // make redis object
                $redis = new RedisCluster(null, $seeds, $this->config['connection_timeout'], $this->config['read_timeout'], false, $this->config['auth'] ?? null);
            } else {
                // make redis object
                $redis = new Redis();
                $redis->connect($this->config['host'][$index], $this->config['port'][$index], $this->config['connection_timeout']);

                // with authentication
                if (!empty($this->config['auth'])) {
                    $redis->auth($this->config['auth'][$index]);
                }

                // resolve connection pool
                if (true === array_key_exists($index, $this->connectionPool)) {
                    $this->connectionPool[$index]->close();
                    $this->connectionPool[$index] = null;
                }

                // set connection pool
                $this->connectionPool[$index] = $redis;
            }

            return $redis;
        } catch (Throwable $ex) {
            // loggin exception
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * manipulate data after get data from redis
     * @param int $dbIndex
     * @param mixed $value
     * @return bool|mixed|string
     * @throws Exception
     */
    private function middlewareForAfterGet(int $dbIndex, $value)
    {
        // decryption
        if (true === RedisUtil::getIsCrypt()) {
            $value = AES::decryptWithHmac(AES::AES_256_CBC, $value, CommonUtil::getSecureConfig(CommonConst::SECURE_REDIS_KEY), true);
        }

        // uncompress
        if (true === RedisUtil::getIsCompress($dbIndex)) {
            $value = unserialize(zstd_uncompress(base64_decode($value)), ['allowed_classes' => false]);
        }

        return $value;
    }

    /**
     * manipulate data before set data to redis
     * @param int $dbIndex
     * @param mixed $value
     * @return bool|string
     * @throws Exception
     */
    private function middlewareForBeforeSet(int $dbIndex, $value)
    {
        // compress
        if (true === RedisUtil::getIsCompress($dbIndex)) {
            $value = base64_encode(zstd_compress(serialize($value)));
        }

        // encryption
        if (true === RedisUtil::getIsCrypt()) {
            $value = AES::encryptWithHmac(AES::AES_256_CBC, $value, CommonUtil::getSecureConfig(CommonConst::SECURE_REDIS_KEY), true);
        }

        return $value;
    }

    /**
     * check redis client type
     * @return bool
     */
    private function isCluster() :bool
    {
        return (isset($this->config['cluster']) && true === $this->config['cluster']);
    }

    /**
     * @return array
     */
    private function getConfig(): array
    {
        /*
         * applicatio sharding을 지원하기 위해 multi redis connect를 지원
         * credential host, port, auth 설정 시 ',' 구분자로 구분
         */
        $credential = CommonUtil::getCredentialConfig('redis');

        // redis type이 cluster 인지 확인
        // cluster key의 값이 "true" 일 경우만 설정 가능
        if (isset($credential['cluster'])) {
            if ($credential['cluster'] === "true") {
                $credential['cluster'] = true;
            } else {
                $credential['cluster'] = false;
            }
        }

        // host, port 지정
        $credential['host'] = explode(',', $credential['host']);
        $credential['port'] = explode(',', $credential['port']);

        // redis required pass 가 지정되어 있는지 확인
        // cluster type이 아닌 경우 multi redis connect 지원을 위해 explode로 파싱
        if (isset($credential['auth'], $credential['cluster']) && $credential['cluster'] !== true) {
            $credential['auth'] =  explode(',', $credential['auth']);
        }

        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $config = $config['settings']['redis'];

        // credential 파일의 설정이 최종 적용될 수 있도록 array_merge의 순서를 config, credential로 변경
        return array_merge($config, $credential);
    }
}
