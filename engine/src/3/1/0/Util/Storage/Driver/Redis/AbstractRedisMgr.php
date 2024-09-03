<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Redis;

use Exception;
use libraries\constant\CommonConst;
use Ntuple\Synctree\Crypt\AES;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Util\CommonUtil;
use Redis;
use RedisCluster;
use RedisException;
use Throwable;

abstract class AbstractRedisMgr implements IRedisMgr
{
    public const REDIS_OPERATION_MODE_SINGLE = 'single';
    public const REDIS_OPERATION_MODE_MULTI = 'multi';
    public const REDIS_OPERATION_MODE_CLUSTER = 'cluster';

    protected $logger;
    protected $config;

    /**
     * AbstractRedisMgr constructor.
     * @param LogMessage $logger
     * @param array|null $config
     * @throws Throwable
     */
    public function __construct(LogMessage $logger, array $config)
    {
        // load config
        $this->config = $this->getConfig($config);
        $this->logger = $logger;
    }

    /**
     * @return LogMessage
     */
    public function getLogger(): LogMessage
    {
        return $this->logger;
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @param bool $isGetFromMiddleware
     * @return mixed|bool|string
     * @throws RedisStorageException|Exception
     */
    public function getData(int $dbIndex, string $key, bool $isGetFromMiddleware = true)
    {
        $key = trim($key);

        try {
            // make connection
            $connection = $this->makeConnection($dbIndex);

            // get data
            if (false !== ($value=$connection->get($key))) {
                $value = $isGetFromMiddleware ?$this->middlewareForAfterGet($dbIndex, $value) :$value;
            }

            return $value;
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to get data[key:'.$key.']');
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @param $value
     * @param bool $isGetFromMiddleware
     * @return bool
     * @throws RedisStorageException|Exception
     */
    public function setData(int $dbIndex, string $key, $value, bool $isGetFromMiddleware = true): bool
    {
        $key = trim($key);

        try {
            // make connection
            $connection = $this->makeConnection($dbIndex);

            // call middleware
            $value = $isGetFromMiddleware ?$this->middlewareForBeforeSet($dbIndex, $value) :$value;

            // set data
            if (true !== ($isSet=$connection->set($key, $value))) {
                throw new RedisStorageException('Failed to set data[key:'.$key.']');
            }

            return $isSet;
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to set data[key:'.$key.']');
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @param int $expireTime
     * @param $value
     * @param bool $isGetFromMiddleware
     * @return bool
     * @throws RedisStorageException|Exception
     */
    public function setDataWithExpire(int $dbIndex, string $key, int $expireTime, $value, bool $isGetFromMiddleware = true): bool
    {
        $key = trim($key);

        try {
            // make connection
            $connection = $this->makeConnection($dbIndex);

            // call middleware
            $value = $isGetFromMiddleware ?$this->middlewareForBeforeSet($dbIndex, $value) :$value;

            // set data with expire time
            if (true !== ($isSet=$connection->setEx($key, $expireTime, $value))) {
                throw new RedisStorageException('Failed to set data[key:'.$key.']');
            }

            return $isSet;
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to set data[key:'.$key.']');
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @param $value
     * @return bool
     * @throws RedisStorageException|Exception
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
     * @throws RedisStorageException|Exception
     */
    public function exist(int $dbIndex, string $key): bool
    {
        $key = trim($key);

        try {
            // make connection
            $connection = $this->makeConnection($dbIndex);

            // check exist key
            return $connection->exists($key);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to exist[key:'.$key.']');
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @return bool
     * @throws RedisStorageException|Exception
     */
    public function del(int $dbIndex, string $key): bool
    {
        $key = trim($key);

        try {
            // make connection
            $connection = $this->makeConnection($dbIndex);

            // delete key
            return 1 === $connection->del($key);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to delete[key:'.$key.']');
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @param int $expireTime
     * @return bool
     * @throws RedisStorageException|Exception
     */
    public function expire(int $dbIndex, string $key, int $expireTime): bool
    {
        $key = trim($key);

        try {
            // make connection
            $connection = $this->makeConnection($dbIndex);

            // update expire time
            return $connection->expire($key, $expireTime);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to set expire[key:'.$key.']');
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @return int
     * @throws RedisStorageException|Exception
     */
    public function incr(int $dbIndex, string $key): int
    {
        $key = trim($key);

        try {
            // make connection
            $connection = $this->makeConnection($dbIndex);

            // increment the number
            return $connection->incr($key);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to increment[key:'.$key.']');
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @return int
     * @throws RedisStorageException|Exception
     */
    public function decr(int $dbIndex, string $key): int
    {
        $key = trim($key);

        try {
            // make connection
            $connection = $this->makeConnection($dbIndex);

            // decrement the number
            return $connection->decr($key);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to decrement[key:'.$key.']');
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @return int
     * @throws RedisStorageException|Exception
     */
    public function getTtl(int $dbIndex, string $key): int
    {
        $key = trim($key);

        try {
            // make connection
            $connection = $this->makeConnection($dbIndex);

            // get remaining time
            return $connection->ttl($key);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to get ttl[key:'.$key.']');
        }
    }

    /**
     * @param int $dbIndex
     * @param string $key
     * @return int
     * @throws RedisStorageException|Exception
     */
    public function getPTtl(int $dbIndex, string $key): int
    {
        $key = trim($key);

        try {
            // make connection
            $connection = $this->makeConnection($dbIndex);

            // get remaining time
            return $connection->pttl($key);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to get pttl[key:'.$key.']');
        }
    }

    /**
     * @param int $dbIndex
     * @param mixed $value
     * @return bool|mixed|string
     * @throws Exception
     */
    private function middlewareForAfterGet(int $dbIndex, $value)
    {
        // decryption
        if (true === $this->getIsCrypt()) {
            $value = AES::create($this->logger)->decryptWithHmac(AES::AES_256_CBC, $value, CommonUtil::getSecureConfig(CommonConst::SECURE_REDIS_KEY), true);
        }

        // uncompress
        if (true === $this->getIsCompress($dbIndex)) {
            $value = unserialize(zstd_uncompress(base64_decode($value)), ['allowed_classes' => false]);
        }

        return $value;
    }

    /**
     * @param int $dbIndex
     * @param mixed $value
     * @return bool|string
     * @throws Exception
     */
    private function middlewareForBeforeSet(int $dbIndex, $value)
    {
        // compress
        if (true === $this->getIsCompress($dbIndex)) {
            $value = base64_encode(zstd_compress(serialize($value)));
        }

        // encryption
        if (true === $this->getIsCrypt()) {
            $value = AES::create($this->logger)->encryptWithHmac(AES::AES_256_CBC, $value, CommonUtil::getSecureConfig(CommonConst::SECURE_REDIS_KEY), true);
        }

        return $value;
    }

    /**
     * @return bool
     */
    private function getIsCrypt(): bool
    {
        return $this->config['crypt'];
    }

    /**
     * @param int|null $dbIndex
     * @return bool
     */
    private function getIsCompress(int $dbIndex = null): bool
    {
        $compressInfo = $this->config['compress'];

        // check compress config is enable
        if (true !== $compressInfo['is_compress']) {
            return false;
        }

        // check compress db list is set
        if (empty($compressInfo['lists'])) {
            return true;
        }

        // check dbindex is null
        if (null === $dbIndex) {
            throw new \RuntimeException('Invalid db index for check compress list');
        }

        return in_array($dbIndex, $compressInfo['lists'], true);
    }

    /**
     * @param array $connectionInfo
     * @return array
     */
    private function getConfig(array $connectionInfo): array
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $config = $config['settings']['redis'];

        return array_merge($config, $connectionInfo);
    }

    /**
     * @param string $key
     * @return int
     */
    protected function getShard(string $key): int
    {
        return ord(substr($key, -1));
    }

    /**
     * @param int $dbIndex
     * @param int|null $shardIndex
     * @param string|null $key
     * @return Redis|RedisCluster
     */
    abstract protected function makeConnection(int $dbIndex, int $shardIndex = null, string $key = null);
    abstract protected function close(): void;
}
