<?php
namespace models\redis;

use libraries\util\RedisUtil;

class RedisKeys
{
    /**
     * @param string $slaveID
     * @return string
     */
    public static function makeLogDbShardKey(string $slaveID): string
    {
        return RedisUtil::middlewareForRedisKey('logdb_shard_info_' . $slaveID);
    }
}
