<?php
namespace middleware\exceptions;

use Throwable;
use Symfony\Component\HttpKernel\Exception\HttpException;

use constants\ResultCode;

class UnknownRateLimitMiddlewareException extends HttpException
{
    public function __construct(Throwable $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct(500, $previous ? $previous->getMessage() : null);
    }

    public function render(): array
    {
        $message = $this->getMessage();
        return [
            'code' => ResultCode::EX_IN_RATE_LIMIT_MIDDLEWARE,
            'message' => "unexpected excaption in rate limit middleware. (prev message : $message)",
        ];
    }
}