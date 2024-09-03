<?php
namespace Ntuple\Synctree\Util\AccessControl\RateLimit;

use Ntuple\Synctree\Util\AccessControl\IStatus;

class Status implements IStatus
{
    private $key;
    private $count;
    private $limit;
    protected $remainingAttempts;

    /**
     * Status constructor.
     * @param string $key
     * @param int $count
     * @param int $limit
     * @param int $remainingAttempts
     */
    public function __construct(string $key, int $count, int $limit, int $remainingAttempts)
    {
        $this->key = $key;
        $this->count = $count;
        $this->limit = $limit;
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
     * @return string
     */
    private function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return int
     */
    private function getCount(): int
    {
        return $this->count;
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
        return $this->getLimit() - $this->getCount();
    }

    /**
     * @return int
     */
    private function getRemainingAttempts(): int
    {
        return $this->remainingAttempts;
    }
}