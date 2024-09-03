<?php
namespace Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket;

class Status implements ITokenBucketStatus
{
    private $key;
    private $limit;
    private $remainingCount;
    private $remainingAttempts;

    /**
     * Status constructor.
     * @param string|null $key
     * @param int $remainingCount
     * @param int $limit
     * @param int $remainingAttempts
     */
    public function __construct(string $key = null, int $limit = 0, int $remainingCount = 0, int $remainingAttempts =  0)
    {
        $this->key = $key;
        $this->limit = $limit;
        $this->remainingCount = $remainingCount;
        $this->remainingAttempts = $remainingAttempts;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return [
            'key' => $this->getKey(),
            'limit' => $this->getLimit(),
            'remaining' => $this->getRemainingCount(),
            'reset' => $this->getRemainingAttempts()
        ];
    }

    /**
     * @return array
     */
    public function getExceptionData(): array
    {
        return [
            'limit' => $this->getLimit(),
            'remaining' => $this->getRemainingCount(),
            'reset' => $this->getRemainingAttempts()
        ];
    }

    /**
     * @return array
     */
    public function getRateLimitHeader(): array
    {
        return [
            'X-Synctree-RateLimit-Limit' => $this->getLimit(),
            'X-Synctree-RateLimit-Remaining' => $this->getRemainingCount(),
            'X-Synctree-RateLimit-Reset' => $this->getRemainingAttempts()
        ];
    }

    /**
     * @param string $key
     */
    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @param int $remainingCount
     */
    public function setRemainingCount(int $remainingCount): void
    {
        $this->remainingCount = $remainingCount;
    }

    /**
     * @param int $remainingAttempts
     */
    public function setRemainingAttempts(int $remainingAttempts): void
    {
        $this->remainingAttempts = $remainingAttempts;
    }

    /**
     * @return int
     */
    public function getRemainingAttempts(): int
    {
        return $this->remainingAttempts;
    }

    /**
     * @return string
     */
    private function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return int
     */
    private function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    private function getRemainingCount(): int
    {
        return $this->remainingCount;
    }
}