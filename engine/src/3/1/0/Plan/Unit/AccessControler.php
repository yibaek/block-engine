<?php
namespace Ntuple\Synctree\Plan\Unit;

use Ntuple\Synctree\Util\AccessControl\IStatus;

class AccessControler
{
    private $rateLimit;

    /**
     * AccessControler constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param IStatus $status
     */
    public function setRateLimitStatus(IStatus $status): void
    {
        $this->rateLimit = $status;
    }

    /**
     * @return array
     */
    public function getRateLimitHeader(): array
    {
        if (empty($this->rateLimit)) {
            return [];
        }

        return $this->rateLimit->getRateLimitHeader();
    }
}