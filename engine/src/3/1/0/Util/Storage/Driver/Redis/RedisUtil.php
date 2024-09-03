<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Redis;

use Exception;
use JsonException;
use Throwable;

class RedisUtil
{
    /**
     * @param IRedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @param bool $isGetFromMiddleware
     * @return mixed|null
     * @throws RedisStorageException|Exception
     */
    public static function getData(IRedisMgr $redis, string $key, int $dbIndex, bool $isGetFromMiddleware = true)
    {
        try {
            // get data from redis
            $resData = $redis->getData($dbIndex, $key, $isGetFromMiddleware);

            // check redis data is null
            if (false === $resData) {
                return null;
            }

            return json_decode($resData, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $ex) {
            return $resData;
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $redis->getLogger()->exception($ex);
            throw new RedisStorageException('Failed to get data[key:'.$key.']');
        }
    }

    /**
     * @param IRedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @return mixed|null
     * @throws RedisStorageException|Exception
     */
    public static function getDataWithDel(IRedisMgr $redis, string $key, int $dbIndex)
    {
        try {
            // get data from redis
            if (false === ($resData=self::getData($redis, $key, $dbIndex))) {
                return null;
            }

            // delete data to redis
            self::del($redis, $key, $dbIndex);

            return $resData;
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $redis->getLogger()->exception($ex);
            throw new RedisStorageException('Failed to get data[key:'.$key.']');
        }
    }

    /**
     * @param IRedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @param $value
     * @param bool $isGetFromMiddleware
     * @return bool
     * @throws RedisStorageException|Exception
     */
    public static function setData(IRedisMgr $redis, string $key, int $dbIndex, $value, bool $isGetFromMiddleware = true): bool
    {
        try {
            // redefine value
            $value = (true === is_array($value)) ?json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE, 512) :$value;

            // set data to redis with expire
            return $redis->setData($dbIndex, $key, $value, $isGetFromMiddleware);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $redis->getLogger()->exception($ex);
            throw new RedisStorageException('Failed to set data[key:'.$key.']');
        }
    }

    /**
     * @param IRedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @param int $expireTime
     * @param $value
     * @param bool $isGetFromMiddleware
     * @return bool
     * @throws RedisStorageException|Exception
     */
    public static function setDataWithExpire(IRedisMgr $redis, string $key, int $dbIndex, int $expireTime, $value, bool $isGetFromMiddleware = true): bool
    {
        try {
            // redefine value
            $value = (true === is_array($value)) ?json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE, 512) :$value;

            // set data to redis with expire
            return $redis->setDataWithExpire($dbIndex, $key, $expireTime, $value, $isGetFromMiddleware);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $redis->getLogger()->exception($ex);
            throw new RedisStorageException('Failed to set data[key:'.$key.']');
        }
    }

    /**
     * @param IRedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @param $value
     * @return bool
     * @throws RedisStorageException|Exception
     */
    public static function setDataNotModifyExpire(IRedisMgr $redis, string $key, int $dbIndex, $value): bool
    {
        try {
            // redefine value
            $value = (true === is_array($value)) ?json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE, 512) :$value;

            // update data to redis
            return $redis->setDataNotModifyExpire($dbIndex, $key, $value);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $redis->getLogger()->exception($ex);
            throw new RedisStorageException('Failed to set data[key:'.$key.']');
        }
    }

    /**
     * @param IRedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @return int
     * @throws RedisStorageException|Exception
     */
    public static function increment(IRedisMgr $redis, string $key, int $dbIndex): int
    {
        try {
            // incr to redis
            return $redis->incr($dbIndex, $key);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $redis->getLogger()->exception($ex);
            throw new RedisStorageException('Failed to increment[key:'.$key.']');
        }
    }

    /**
     * @param IRedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @return int
     * @throws RedisStorageException|Exception
     */
    public static function decrement(IRedisMgr $redis, string $key, int $dbIndex): int
    {
        try {
            // decr to redis
            return $redis->decr($dbIndex, $key);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $redis->getLogger()->exception($ex);
            throw new RedisStorageException('Failed to decrement[key:'.$key.']');
        }
    }

    /**
     * @param IRedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @return bool
     * @throws RedisStorageException|Exception
     */
    public static function del(IRedisMgr $redis, string $key, int $dbIndex): bool
    {
        try {
            // delete data to redis
            return $redis->del($dbIndex, $key);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $redis->getLogger()->exception($ex);
            throw new RedisStorageException('Failed to delete[key:'.$key.']');
        }
    }

    /**
     * @param IRedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @return bool
     * @throws RedisStorageException|Exception
     */
    public static function exist(IRedisMgr $redis, string $key, int $dbIndex): bool
    {
        try {
            // check exist key in redis
            $resData = $redis->exist($dbIndex, $key);

            return (1 === $resData || true === $resData);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $redis->getLogger()->exception($ex);
            throw new RedisStorageException('Failed to exist[key:'.$key.']');
        }
    }

    /**
     * @param IRedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @param int $expireTime
     * @return bool
     * @throws RedisStorageException|Exception
     */
    public static function expire(IRedisMgr $redis, string $key, int $dbIndex, int $expireTime): bool
    {
        try {
            // set redis key expire
            return $redis->expire($dbIndex, $key, $expireTime);
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $redis->getLogger()->exception($ex);
            throw new RedisStorageException('Failed to set expire[key:'.$key.']');
        }
    }

    /**
     * @param IRedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @param bool $milliseconds
     * @return int
     * @throws RedisStorageException|Exception
     */
    public static function getTtl (IRedisMgr $redis, string $key, int $dbIndex, bool $milliseconds = false): int
    {
        try {
            // get redis ttl
            if (true === $milliseconds) {
                $resData = $redis->getPTtl($dbIndex, $key);
            } else {
                $resData = $redis->getTtl($dbIndex, $key);
            }

            return $resData;
        } catch (RedisStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $redis->getLogger()->exception($ex);
            throw new RedisStorageException('Failed to get ttl[key:'.$key.']');
        }
    }
}
