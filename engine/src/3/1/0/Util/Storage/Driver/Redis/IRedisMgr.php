<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Redis;

use Ntuple\Synctree\Log\LogMessage;

interface IRedisMgr
{
    public function getLogger(): LogMessage;
    public function getData(int $dbIndex, string $key, bool $isGetFromMiddleware = true);
    public function setData(int $dbIndex, string $key, $value, bool $isGetFromMiddleware = true): bool;
    public function setDataWithExpire(int $dbIndex, string $key, int $expireTime, $value, bool $isGetFromMiddleware = true): bool;
    public function setDataNotModifyExpire(int $dbIndex, string $key, $value): bool;
    public function exist(int $dbIndex, string $key): bool;
    public function del(int $dbIndex, string $key): bool;
    public function expire(int $dbIndex, string $key, int $expireTime): bool;
    public function incr(int $dbIndex, string $key): int;
    public function decr(int $dbIndex, string $key): int;
    public function getTtl(int $dbIndex, string $key): int;
    public function getPTtl(int $dbIndex, string $key): int;
}