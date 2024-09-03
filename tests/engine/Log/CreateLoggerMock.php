<?php declare(strict_types=1);

namespace Tests\engine\Log;

use Monolog\Handler\TestHandler;
use Ntuple\Synctree\Log\CreateLogger;

/**
 * 로그를 파일에 쓰지 않는 CreateLogger Mock 구현
 *
 * @since SYN-639
 */
class CreateLoggerMock extends CreateLogger
{
    public function __construct(array $config = null)
    {
        parent::__construct($config);
        $this->getLogger()->popHandler();
        $this->getLogger()->pushHandler(new TestHandler());
    }
}