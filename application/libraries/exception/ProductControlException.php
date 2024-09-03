<?php
namespace libraries\exception;

use RuntimeException;
use Throwable;

class ProductControlException extends RuntimeException
{
    private $status;
    private $error;

    /**
     * ProductControlException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $this->status = 200;
        $this->error = [];
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param int $status
     * @param string $error
     * @param string $description
     * @return $this
     */
    public function setError(int $status, string $error, string $description): self
    {
        $this->status = $status;
        $this->error = [
            'error' => $error,
            'error_description' => $description
        ];
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array
     */
    public function getBody(): array
    {
        return $this->error;
    }
}