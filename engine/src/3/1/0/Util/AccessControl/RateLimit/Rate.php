<?php
namespace Ntuple\Synctree\Util\AccessControl\RateLimit;

class Rate
{
    private $limit;
    private $interval;

    /**
     * Rate constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function perSecond(int $limit): Rate
    {
        $this->limit = $limit;
        $this->interval = 1;
        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function perMinute(int $limit): Rate
    {
        $this->limit = $limit;
        $this->interval = 60;
        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function perHour(int $limit): Rate
    {
        $this->limit = $limit;
        $this->interval = 3600;
        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function perDay(int $limit): Rate
    {
        $this->limit = $limit;
        $this->interval = 86400;
        return $this;
    }

    /**
     * @param int $limit
     * @param int $interval
     * @return $this
     */
    public function perCustom(int $limit, int $interval): Rate
    {
        $this->limit = $limit;
        $this->interval = $interval;
        return $this;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    public function getInterval(): int
    {
        return $this->interval;
    }
}