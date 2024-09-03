<?php
namespace libraries\log;

use Exception;

use libraries\util\CommonUtil;
use libraries\log\formatter\LogstashFormatter;
use libraries\log\processor\TimestampCorrectionProcessor;
use Monolog\Logger;

/**
 * Bizunit console log를 파일로 처리하기 위한 확장.
 *
 * @since SYN-187
 */
class ConsoleLogger extends CreateLogger
{
    private $config;

    /**
     * @param array|null $config 설정 파일로부터 가져오는 기본값을 덮어쓰는 추가 설정.
     * @throws Exception
     */
    public function __construct(array $config = null)
    {
        $this->config = $this->mergeConfigs($config);
        parent::__construct($this->config);

        $this->getLogger()->pushProcessor(new TimestampCorrectionProcessor());

        foreach ($this->getLogger()->getHandlers() as $handler) {
            $handler->setFormatter(new LogstashFormatter($this->config['name']));
        }
    }


    /**
     * console log 출력을 위한 설정을 정리한다.
     * logger config -> credential의 console-log 섹션, 생성자 입력 값 순으로 덮어씀.
     * console-log.log-level에 의해 필터링이 적용되므로, 해당 설정을 Log handler에도 적용.
     *
     * @param array|null $additional 생성자에 지정된 추가 설정
     */
    private function mergeConfigs(array $additional = null): array
    {
        $basis = CommonUtil::getLoggerConfig();
        $credential = CommonUtil::getCredentialConfig('console-log');

        if (array_key_exists('log-level', $credential) && $this->isValidLevel($credential['log-level'])) {
            $credential['level'] = (int) $credential['log-level'];
        }

        return array_merge($basis, $credential, $additional ?? []);
    }


    /**
     * console-log 섹션에 지정된 Log level이 정확한 monolog level에 해당하는지 확인.
     *
     * @param $value mixed
     * @return bool monolog level 값에 해당하면 참.
     */
    private function isValidLevel($value): bool {
        return !empty($value) && in_array($value, array_values(Logger::getLevels()));
    }


    /** config을 기준으로 출력될 파일 경로를 지정. */
    protected function makeLogFileName(): string
    {
        $filename = $this->config['filename'] ?? 'console';
        return $this->trimPath($this->config['path']) . $filename . '.log';
    }


    /** 파일 경로 정리 */
    private function trimPath(string $input): string
    {
        $path = trim($input);
        if ($path === '') {
            return $path;
        }
        return $path[-1] === '/' ? $path : $path . '/';
    }

}
