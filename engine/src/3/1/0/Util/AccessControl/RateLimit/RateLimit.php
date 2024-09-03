<?php
namespace Ntuple\Synctree\Util\AccessControl\RateLimit;

use libraries\constant\CommonConst;
use Ntuple\Synctree\Models\Redis\RedisKeys;
use Ntuple\Synctree\Models\Redis\RedisMgr;
use Ntuple\Synctree\Util\AccessControl\Exception\LimitExceededException;
use Ntuple\Synctree\Util\RedisUtil;
use Throwable;

class RateLimit
{
    private $redis;
    private $db;
    private $group;

    /**
     * RateLimit constructor.
     * @param RedisMgr $redis
     * @param string $group
     */
    public function __construct(RedisMgr $redis, string $group = '')
    {
        $this->redis = $redis;
        $this->group = $group;
        $this->db = CommonConst::ACCESS_CONTROL_REDIS_DB;
    }

    /**
     * @param string $key
     * @param Rate $rate
     * @return Status
     * @throws Throwable
     */
    public function limit(string $key, Rate $rate): Status
    {
        // generate key
        $key = $this->key($key, $rate->getInterval());

        // get current call count
        $current = $this->getCurrent($key);

        // check limit exceeded
        if ($current >= ($rate->getLimit())) {
            throw LimitExceededException::for((new Status($key, $current, $rate->getLimit(), $this->ttl($key))));
        }

        // update call count
        $current = $this->updateCounter($key, $rate->getInterval());

        return (new Status($key, $current, $rate->getLimit(), $this->ttl($key)));
    }

    /**
     * @param string $key
     * @param Rate $rate
     * @return Status
     * @throws Throwable
     */
    public function control(string $key, Rate $rate): Status
    {
        // generate key
        $key = $this->key($key, null);

        // get current call count
        $current = $this->getCurrent($key);

        // check limit exceeded
        if ($current >= ($rate->getLimit())) {
            throw LimitExceededException::for((new Status($key, $current, $rate->getLimit(), $this->ttl($key))));
        }

        // update call count
        $current = $this->updateCounter($key, $rate->getInterval());

        return (new Status($key, $current, $rate->getLimit(), $this->ttl($key)));
    }

    /**
     * @param string $key
     * @param int|null $interval
     * @return string
     */
    private function key(string $key, int $interval = null): string
    {
        return RedisKeys::makeRateLimitKeyForAccessControl($key, $this->group);
    }

    /**
     * @param string $key
     * @return int
     * @throws Throwable
     */
    private function getCurrent(string $key): int
    {
        return RedisUtil::getData($this->redis, $key, $this->db, false);
    }

    /**
     * @param string $key
     * @param int $interval
     * @return int
     * @throws Throwable
     */
    private function updateCounter(string $key, int $interval): int
    {
        // get current call count
        $current = RedisUtil::increment($this->redis, $key, $this->db);

        // set expiredate; if first
        if ($current === 1) {
            RedisUtil::expire($this->redis, $key, $this->db, $interval);
        }

        return $current;
    }

    /**
     * @param string $key
     * @return int
     * @throws Throwable
     */
    private function ttl(string $key): int
    {
        return max((int)ceil(RedisUtil::getTtl($this->redis, $key, $this->db, true) / 1000), 0);
    }
}