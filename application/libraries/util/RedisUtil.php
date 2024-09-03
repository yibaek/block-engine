<?php
namespace libraries\util;

use Exception;
use libraries\log\LogMessage;
use models\redis\RedisMgr;
use Throwable;

class RedisUtil
{
    private const MASK = '*hidden*';

    /**
     * get redis shard info with redis key
     * @param string $key
     * @return int
     */
    public static function getShard(string $key): int
    {
        return ord(substr($key, -1));
    }

    /**
     * get is crypt of redis env
     * @return bool
     */
    public static function getIsCrypt(): bool
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        return $config['settings']['redis']['crypt'];
    }

    /**
     * get is compress of redis env
     * @param int|null $dbIndex
     * @return bool
     * @throws Exception
     */
    public static function getIsCompress(int $dbIndex = null): bool
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $compressInfo = $config['settings']['redis']['compress'];

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
            throw new \RuntimeException('invalid db number for check compress list');
        }

        return in_array($dbIndex, $compressInfo['lists'], true);
    }

    /**
     * get data from redis
     *
     * @param RedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @param bool $logUserData 캐시 내용을 디버그 로그에 표시할 것인지 결정
     * @return bool|mixed|string 캐시 값. json 형식일 경우 decode 처리된 결과를 반환
     * @throws Throwable
     * @throws \JsonException
     */
    public static function getData(RedisMgr $redis, string $key, int $dbIndex, bool $logUserData = true)
    {
        // generates a backtrace
        $debugInfo = debug_backtrace();

        // get data from redis
        $resData = $redis->getData($dbIndex, $key);

        // logging tracelog
        LogMessage::debug('key[' . $key . ']_res[' . ($logUserData ? $resData : self::MASK) . ']', $debugInfo[1]['class'], $debugInfo[0]['function'], $debugInfo[0]['line']);

        // check redis data is null
        if (false === $resData) {
            return false;
        }

        // if json type, return with json_decode
        if (null !== ($_resData= json_decode($resData, true, 512, JSON_THROW_ON_ERROR))) {
            return $_resData;
        }

        return $resData;
    }

    /**
     * get redis data with del
     * @param RedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @param bool $logUserData 캐시 내용을 디버그 로그에 표시할 것인지 결정
     * @return bool|mixed|string 캐시 값. json 형식일 경우 decode 처리된 결과를 반환
     * @throws Throwable
     * @throws \JsonException
     */
    public static function getDataWithDel(RedisMgr $redis, string $key, int $dbIndex, bool $logUserData = true)
    {
        // generates a backtrace
        $debugInfo = debug_backtrace();

        // get data from redis
        if (false === ($resData=self::getData($redis, $key, $dbIndex, $logUserData))) {
            // logging tracelog
            LogMessage::debug('key['.$key.']_res['.$resData.']', $debugInfo[1]['class'], $debugInfo[0]['function'], $debugInfo[0]['line']);
            return false;
        }

        // delete data to redis
        if (false === self::del($redis, $key, $dbIndex)) {
            // logging tracelog
            LogMessage::error('key['.$key.']_res[failed to delete redis data]', $debugInfo[1]['class'], $debugInfo[0]['function'], $debugInfo[0]['line']);
        }

        return $resData;
    }

    /**
     * set redis data with expire time
     *
     * @param RedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @param int $expireTime
     * @param $value
     * @param bool $logUserData 캐시 내용을 디버그 로그에 표시할 것인지 결정
     * @return bool
     * @throws Throwable
     * @throws \JsonException
     */
    public static function setDataWithExpire(RedisMgr $redis, string $key, int $dbIndex, int $expireTime, $value, bool $logUserData = true): bool
    {
        // generates a backtrace
        $debugInfo = debug_backtrace();

        // redefine value
        $value = (true === is_array($value)) ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) :$value;

        // set data to redis with expire
        $resData = $redis->setDataWithExpire($dbIndex, $key, $expireTime, $value);

        // logging tracelog
        LogMessage::debug('key[' . $key . ']_req[' . ($logUserData ? $value : self::MASK) . ']_res[' . $resData . ']', $debugInfo[1]['class'], $debugInfo[0]['function'], $debugInfo[0]['line']);

        return $resData;
    }

    /**
     * set redis data without expire time
     * @param RedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @param $value
     * @param bool $logUserData 캐시 내용을 디버그 로그에 표시할 것인지 결정
     * @return bool
     * @throws Throwable
     * @throws \JsonException
     */
    public static function setDataNotModifyExpire(RedisMgr $redis, string $key, int $dbIndex, $value, bool $logUserData = true): bool
    {
        // generates a backtrace
        $debugInfo = debug_backtrace();

        // redefine value
        $value = (true === is_array($value)) ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) :$value;

        // update data to redis
        $resData = $redis->setDataNotModifyExpire($dbIndex, $key, $value);

        // logging tracelog
        LogMessage::debug('key['.$key.']_req['.($logUserData ? $value : self::MASK).']_res['.$resData.']', $debugInfo[1]['class'], $debugInfo[0]['function'], $debugInfo[0]['line']);

        return $resData;
    }

    /**
     * get redis increment number
     * @param RedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @return int
     * @throws Throwable
     */
    public static function getIncr(RedisMgr $redis, string $key, int $dbIndex): int
    {
        // generates a backtrace
        $debugInfo = debug_backtrace();

        // incr to redis
        $resData = $redis->incr($dbIndex, $key);

        // logging tracelog
        LogMessage::debug('key['.$key.']_res['.$resData.']', $debugInfo[1]['class'], $debugInfo[0]['function'], $debugInfo[0]['line']);

        return $resData;
    }

    /**
     * delete data to redis
     * @param RedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @return bool
     * @throws Throwable
     */
    public static function del(RedisMgr $redis, string $key, int $dbIndex): bool
    {
        // generates a backtrace
        $debugInfo = debug_backtrace();

        // delete data to redis
        $resData = $redis->del($dbIndex, $key);

        // logging tracelog
        LogMessage::debug('key['.$key.']_res['.$resData.']', $debugInfo[1]['class'], $debugInfo[0]['function'], $debugInfo[0]['line']);

        return $resData;
    }

    /**
     * check exist redis key
     * @param RedisMgr $redis
     * @param string $key
     * @param int $dbIndex
     * @return bool
     * @throws Throwable
     */
    public static function exist(RedisMgr $redis, string $key, int $dbIndex): bool
    {
        // generates a backtrace
        $debugInfo = debug_backtrace();

        // check exist key in redis
        $resData = $redis->exist($dbIndex, $key);

        // logging tracelog
        LogMessage::debug('key['.$key.']_res['.$resData.']', $debugInfo[1]['class'], $debugInfo[0]['function'], $debugInfo[0]['line']);

        return (1 === $resData || true === $resData);
    }

    /**
     * @param string $key
     * @return string
     */
    public static function middlewareForRedisKey(string $key): string
    {
        if (true === self::getIsCrypt()) {
            $key = CommonUtil::getHashKey($key, 'md5');
        }

        return $key;
    }
}
