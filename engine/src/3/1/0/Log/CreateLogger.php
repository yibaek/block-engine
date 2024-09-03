<?php
namespace Ntuple\Synctree\Log;

use Exception;
use Ntuple\Synctree\Util\CommonUtil;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class CreateLogger
{
    private $logger;
    private $config;

    /**
     * CreateLogger constructor.
     * @param array|null $config
     * @throws Exception
     */
    public function __construct(array $config = null)
    {
        // set log config
        $this->config = empty($config) ?$this->getLogConfig() :$config;

        // get logger
        $this->logger = new Logger($this->config['name']);

        // set handler
        $handler = new StreamHandler($this->makeLogFileName(), $this->config['level'], true, $this->getFilePermission());

        // LineFormatter 의 기본 포맷에서 channel 제거 : "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
        // 변경 후 예시) [2022-02-07 11:59:03.274502] DEBUG: 로그메세지 {context_json} {extra_json}
        $handler->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message% %context% %extra%\n", 'Y-m-d H:i:s.u'));

        // push handler
        $this->logger->pushHandler($handler);
    }

    /**
     * @param $processor
     * @return $this
     */
    public function addProcessor($processor): CreateLogger
    {
        $this->logger->pushProcessor($processor);
        return $this;
    }

    /**
     * last processor 제거
     * @return $this
     */
    public function popProcessor(): CreateLogger
    {
        if (!$this->logger->getProcessors()) { // processor가 없으면 그대로 return
            return $this;
        }
        $this->logger->popProcessor();
        return $this;
    }

    /**
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * @return array
     */
    private function getLogConfig(): array
    {
        return CommonUtil::getLoggerConfig();
    }

    /**
     * @return string
     */
    protected function makeLogFileName(): string
    {
        return $this->config['path'] . date('Ymd') . '.log';
    }

    /**
     * @return int|null
     */
    protected function getFilePermission(): ?int
    {
        return null;
    }
}
