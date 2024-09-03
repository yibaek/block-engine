<?php
namespace libraries\log;

use DateTime;
use Throwable;
use Monolog\Logger;

/**
 * bizunit console log 파일 출력 처리
 *
 * @since SYN-187
 */
class ConsoleLogFileHandler
{
    /** @var $logger Logger */
    private $logger;

    public function __construct(ConsoleLogger $logger) {
        $this->logger = $logger->getLogger();
    }

    /**
     * @param array $accountInfo
     * @param array $bizunitInfo
     * @param array $message
     * @return bool
     */
    public function setConsoleLog(array $accountInfo, array $bizunitInfo, array $message): bool
    {
        try {
            foreach ($message as $item) {
                $context = [
                    'master_id' => $accountInfo['master_id'],
                    'slave_id' => $accountInfo['slave_id'],

                    'bizunit_sno' => $bizunitInfo['bizunit-sno'],
                    'bizunit_id' => $bizunitInfo['bizunit_id'],
                    'bizunit_version' => $bizunitInfo['bizunit_version'],
                    'revision_sno' => $bizunitInfo['revision-sno'],
                    'revision_id' => $bizunitInfo['revision_id'],
                    'environment' => $bizunitInfo['environment'],

                    'console_level' => $item['level'],
                    'console_type' => $item['type'],
                    'transaction_key' => $bizunitInfo['transaction_key'],
                    // 실제 콘솔 로그 작성 시점을 별도로 보존.
                    '__timestamp'=> DateTime::createFromFormat('U.u', $item['date']),
                ];

                $this->logger->addRecord($item['level'], $item['message'], $context);
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            return false;
        }
    }
}
