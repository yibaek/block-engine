<?php declare(strict_types=1);

namespace Tests\libraries;

/**
 * Mock 구현되지 않은 test stub
 *
 * @since SYN-672
 */
class NotImplementedException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Not implemented');
    }

}