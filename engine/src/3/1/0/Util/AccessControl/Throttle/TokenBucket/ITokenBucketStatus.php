<?php
namespace Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket;

use Ntuple\Synctree\Util\AccessControl\IStatus;

interface ITokenBucketStatus extends IStatus
{
    public function setKey(string $key): void;
    public function setLimit(int $limit): void;
    public function setRemainingCount(int $remainingCount): void;
    public function setRemainingAttempts(int $remainingAttempts): void;
    public function getRemainingAttempts(): int;
}