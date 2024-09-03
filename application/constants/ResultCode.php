<?php
namespace constants;

class ResultCode
{
    // Rate Limit Middleware 예외 관련 코드
    public const TOO_MANY_REQUESTS = "TOO_MANY_REQUESTS";
    public const EX_IN_RATE_LIMIT_MIDDLEWARE = "EX_IN_RATE_LIMIT_MIDDLEWARE";
}