<?php
namespace libraries\log;

use Exception;
use libraries\log\processor\TransactionProcessor;
use libraries\util\CommonUtil;
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

        // set option
        $this->logger->pushProcessor(new TransactionProcessor());

        // set handler
        $handler = new StreamHandler($this->makeLogFileName(), $this->config['level']);

        // LineFormatter 의 기본 포맷에서 channel 제거 : "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
        // 변경 후 예시) [2022-02-07 11:59:03.274502] DEBUG: 로그메세지 {context_json} {extra_json}
        $handler->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message% %context% %extra%\n", 'Y-m-d H:i:s.u'));

        // push handler
        $this->logger->pushHandler($handler);
    }

    /**
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * Logger 설정 획득
     *
     * 기본적으로는 application/config/*.php 파일에 설정해 둔 logger 설정을 사용한다.
     *
     * @return array
     */
    private function getLogConfig(): array
    {
        $config = CommonUtil::getLoggerConfig();
        return $this->getAppLogLevelAppliedLogConfig($config);
    }

    /**
     * 주어진 logger 설정의 level 정보를 credentials의 레벨 설정값에 따라 조건부로 덮어써 준다.
     *
     * @see https://redmine.nntuple.com/issues/6384
     * @param array $config
     * @return array
     */
    private function getAppLogLevelAppliedLogConfig(array $config): array
    {
        $appLogConfig = CommonUtil::getCredentialConfig('app-log', false);
        if (!empty($appLogConfig)) {

            // 특수한 것부터 먼저
            $appLogKeys = ['tool', 'general'];
            foreach ($appLogKeys as $appLogKey) {
                $key = "level-$appLogKey";
                if (isset($appLogConfig[$key]) && in_array(strtolower($appLogConfig[$key]), ['debug', 'info', 'warning', 'error'])) {
                    $config['level'] = $appLogConfig[$key];
                    return $config;
                }
            }
        }
        return $config;
    }

    /**
     * @return string
     */
    protected function makeLogFileName(): string
    {
        return $this->config['path'] . date('Ymd') . '.log';
    }
}
