<?php declare(strict_types=1);

namespace Tests\engine\Log;

use Exception;
use Monolog\Logger;
use Ntuple\Synctree\Log\LogMessage;

/**
 * 로그를 파일에 쓰지 않는 LogMessage Mock
 *
 * @since SYN-639
 */
class LogMessageMock extends LogMessage
{
    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct(new CreateLoggerMock([
            'name' => 'synctree-engine-test',
            'path' => __DIR__ . '/../../logs/',
            'level' => Logger::DEBUG
        ]));
    }

}