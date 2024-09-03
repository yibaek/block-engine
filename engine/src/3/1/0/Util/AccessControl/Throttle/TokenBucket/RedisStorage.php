<?php
namespace Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket;

use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Models\Redis\RedisKeys;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Exception\StorageException;
use Redis;
use RedisCluster;
use RedisException;
use malkusch\lock\mutex\PHPRedisMutex;

class RedisStorage
{
    private const LOCK_TIMEOUT = 10;

    private $mutex;
    private $redis;
    private $group;
    private $key;
    private $expiry;

    /**
     * @param Redis|RedisCluster $redis
     * @param LogMessage $logger
     * @param string $key
     * @param int $interval
     * @param int $expiry
     * @param string $group
     */
    public function __construct($redis, LogMessage $logger, string $key, int $interval, int $expiry, string $group = 'access-control-throttle')
    {
        $this->group = $group;
        $this->expiry = $expiry;
        $this->key = $this->key($key, $interval);
        $this->redis = $redis;
        $this->mutex = new PHPRedisMutex([$redis], $key, self::LOCK_TIMEOUT);
        $this->mutex->setLogger($logger->getLogger()->getLogger());
    }

    /**
     * @param float $microtime
     */
    public function bootstrap(float $microtime): void
    {
        $this->setMicrotime($microtime);
    }

    /**
     * @return bool|int
     */
    public function isBootstrapped()
    {
        try {
            return $this->redis->exists($this->key);
        } catch (RedisException $e) {
            throw new StorageException('Failed to check for key existence', 0, $e);
        }
    }

    public function remove(): void
    {
        try {
            if (!$this->redis->del($this->key)) {
                throw new StorageException('Failed to delete key');
            }
        } catch (RedisException $e) {
            throw new StorageException('Failed to delete key', 0, $e);
        }
    }

    /**
     * @param float $microtime
     */
    public function setMicrotime(float $microtime): void
    {
        try {
            if (!$this->redis->setex($this->key, $this->expiry, $microtime)) {
                throw new StorageException('Failed to store microtime');
            }
        } catch (RedisException $e) {
            throw new StorageException('Failed to store microtime', 0, $e);
        }
    }

    /**
     * @return float
     */
    public function getMicrotime(): float
    {
        try {
            $data = $this->redis->get($this->key);
            if ($data === false) {
                throw new StorageException('Failed to get microtime');
            }
            return $data;
        } catch (RedisException $e) {
            throw new StorageException('Failed to get microtime', 0, $e);
        }
    }

    /**
     * @return PHPRedisMutex
     */
    public function getMutex(): PHPRedisMutex
    {
        return $this->mutex;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $key
     * @param int|null $interval
     * @return string
     */
    private function key(string $key, int $interval = null): string
    {
        return RedisKeys::makeThrottleKeyForAccessControl($key, $this->group);
    }
}