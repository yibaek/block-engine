<?php
namespace Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket;

use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Exception\InvalidArgumentException;

class Rate
{
    private $limit;
    private $interval;

    /**
     * Rate constructor.
     * @param int $limit
     * @param int $interval
     */
    public function __construct(int $limit, int $interval)
    {
        if ($limit <= 0) {
            throw new InvalidArgumentException('Amount of tokens should be greater then 0.');
        }

        $this->limit = $limit;
        $this->interval = $interval;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return float
     */
    public function getTokensPerSecond(): float
    {
        return $this->limit / $this->interval;
    }
}
