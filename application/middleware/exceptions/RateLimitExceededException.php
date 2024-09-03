<?php
namespace middleware\exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use constants\ResultCode;

class RateLimitExceededException extends HttpException
{
    public function __construct()
    {
        parent::__construct(429);
    }

    public function render(): array
    {
        return [
            'code' => ResultCode::TOO_MANY_REQUESTS,
            'message' => 'Too Many Requests',
        ];
    }
}