<?php
namespace Ntuple\Synctree\Exceptions\Inner;

use RuntimeException;
use Throwable;

class SynctreeInnerException extends RuntimeException
{
    /**
     * SynctreeInnerException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}