<?php
namespace Ntuple\Synctree\Models\Redis;

use Ntuple\Synctree\Constant\PlanConst;
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\RedisUtil;

class RedisKeys
{
    /**
     * @return string
     */
    public static function makeSecureProtocolKey(): string
    {
        return RedisUtil::middlewareForRedisKey(self::addInnerPrefixKey(uniqid('', true).CommonUtil::getUsec(true)));
    }

    /**
     * @param array $data
     * @param string $postFix
     * @return string
     */
    public static function makeAccessControlDefaultKey(array $data, string $postFix = ''): string
    {
        return RedisUtil::middlewareForRedisKey(CommonUtil::getHashKey(implode('-', $data).'-'.$postFix, 'md5'));
    }

    /**
     * @param string $key
     * @param int|null $interval
     * @param string $group
     * @return string
     */
    public static function makeRateLimitKeyForAccessControl(string $key, string $group = '', int $interval = null): string
    {
        $key = !empty($group) ?$group.':'.$key :$key;
        if (null === $interval) {
            return RedisUtil::middlewareForRedisKey($key);
        }

        return RedisUtil::middlewareForRedisKey($key.'-'.$interval);
    }

    /**
     * @param string $key
     * @param int|null $interval
     * @param string $group
     * @return string
     */
    public static function makeThrottleKeyForAccessControl(string $key, string $group = '', int $interval = null): string
    {
        $key = !empty($group) ?$group.':'.$key :$key;
        if (null === $interval) {
            return RedisUtil::middlewareForRedisKey($key);
        }

        return RedisUtil::middlewareForRedisKey($key.'-'.$interval);
    }

    /**
     * @param string $masterID
     * @param string $group
     * @return string
     */
    public static function makeNosqlCommonQuotaLimitKey(string $masterID, string $group = ''): string
    {
        return RedisUtil::middlewareForRedisKey($group.':'.$masterID);
    }

    /**
     * @param string $key
     * @return string
     */
    private static function addInnerPrefixKey(string $key): string
    {
        return PlanConst::SYNCTREE_REDIS_INNER_KEY_PREFIX .'_'. $key;
    }
}
