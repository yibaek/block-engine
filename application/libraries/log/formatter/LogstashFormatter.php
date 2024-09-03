<?php

namespace libraries\log\formatter;

use Monolog\Formatter\LogstashFormatter as BaseFormatter;

/**
 * 로그 수집기 처리과정 단순화를 위해 logstash v1 포맷으로 출력
 *
 * @since SYN-187
 */
class LogstashFormatter extends BaseFormatter
{
    /** @var string 로그 포맷 힌트 */
    private const TYPE_NAME = 'log-v1';

    public function __construct($applicationName, $extraPrefix = null, $contextPrefix = '')
    {
        parent::__construct(
            $applicationName,
            null,
            $extraPrefix,
            $contextPrefix,
            self::V1
        );
    }

    protected function formatV1(array $record): array
    {
        $output = parent::formatV1($record);
        $output['type'] = self::TYPE_NAME;

        return $output;
    }
}
