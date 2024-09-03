<?php
namespace Ntuple\Synctree\Util\AccessControl\Exception;

use Ntuple\Synctree\Util\AccessControl\IStatus;

class LimitExceededException extends UtilAccessControlException
{
    private $status;

    /**
     * @param IStatus $status
     * @return static
     */
    public static function for(IStatus $status): self
    {
        $exception = new self('Rate limit exceeded');
        $exception->status = $status;

        return $exception;
    }

    /**
     * @return IStatus
     */
    public function getStatus(): IStatus
    {
        return $this->status;
    }
}
