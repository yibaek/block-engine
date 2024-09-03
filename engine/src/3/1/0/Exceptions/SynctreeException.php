<?php
namespace Ntuple\Synctree\Exceptions;

use Exception;
use Ntuple\Synctree\Exceptions\Contexts\ExceptionContext;
use RuntimeException;
use Throwable;

class SynctreeException extends RuntimeException
{
    /**
     * @var ExceptionContext
     * @since SYN-389
     */
    private $context;

    /**
     * SynctreeException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param Exception $class
     * @param string $message
     * @return string
     */
    protected function makeMessage(Exception $class, string $message): string
    {
        return $message;
    }

    /**
     * @since SYN-389
     * @param ExceptionContext $context
     * @return $this
     */
    public function setContext(ExceptionContext $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * @since SYN-389
     * @return ExceptionContext
     */
    public function getContext(): ExceptionContext
    {
        return $this->context;
    }

    /**
     * @return bool 바인딩된 컨텍스트가 있으면 참
     */
    public function hasContext(): bool
    {
        return $this->context !== null;
    }
}