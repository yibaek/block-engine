<?php
namespace Ntuple\Synctree\Util\Exception;

use Ntuple\Synctree\Exceptions\Contexts\ExceptionContext;
use RuntimeException;

/**
 * end-user 에게 제공되지 않는, Util package 안에서 catch 되어야 하는 예외
 */
class UtilException extends RuntimeException
{
    /** @var ExceptionContext */
    private $context;

    /**
     * @since SYN-389
     * @return void
     */
    public function setContext(ExceptionContext $context): self {
        $this->context = $context;
        return $this;
    }

    /**
     * @since SYN-389
     * @return void
     */
    public function getContext(): ExceptionContext {
        return $this->context;
    }
}