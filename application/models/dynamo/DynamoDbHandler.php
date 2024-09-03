<?php
namespace models\dynamo;

use Aws\DynamoDb\Exception\DynamoDbException;
use DateTime;
use libraries\log\LogMessage;
use libraries\util\CommonUtil;
use libraries\util\DynamoDbUtil;
use Throwable;

class DynamoDbHandler
{
    private const TABLE_NAME_CONSOLE_LOG = 'console_log';

    /**
     * @param DynamoDbMgr $dynamodb
     * @param array $accountInfo
     * @param array $bizunitInfo
     * @param array $message
     * @param int $expireDate
     * @return bool|null
     * @throws Throwable
     */
    public static function setConsoleLog(DynamoDbMgr $dynamodb, array $accountInfo, array $bizunitInfo, array $message, int $expireDate): ?bool
    {
        try {
            return $dynamodb->putItem([
                'TableName' => $dynamodb->makeTableName(self::TABLE_NAME_CONSOLE_LOG),
                'Item' => $dynamodb->getMarshalItem([
                    'log_id' => DynamoDbUtil::makeConsoleLogID($bizunitInfo),
                    'environment' => $bizunitInfo['environment'],
                    'log_key' => DynamoDbUtil::makeConsoleLogKey($bizunitInfo),
                    'master_id' => (int) $accountInfo['master_id'],
                    'slave_id' => (int) $accountInfo['slave_id'],
                    'app_id' => (int) $bizunitInfo['appid'],
                    'bizunit_sno' => (int) $bizunitInfo['bizunit-sno'],
                    'transaction_key' => $bizunitInfo['transaction_key'],
                    'message' => $dynamodb->compressWithEncrypt($message),
                    'reg_date' => date('Y-m-d H:i:s').'.'.CommonUtil::getUsec(),
                    'expire_date' => $expireDate
                ])
            ]);
        } catch (DynamoDbException $ex) {
            if ($ex->getAwsErrorCode() === 'ValidationException' && $ex->getAwsErrorMessage() === 'Item size has exceeded the maximum allowed size') {
                $dynamodb->putItem([
                    'TableName' => $dynamodb->makeTableName(self::TABLE_NAME_CONSOLE_LOG),
                    'Item' => $dynamodb->getMarshalItem([
                        'log_id' => DynamoDbUtil::makeConsoleLogID($bizunitInfo),
                        'environment' => $bizunitInfo['environment'],
                        'log_key' => DynamoDbUtil::makeConsoleLogKey($bizunitInfo),
                        'master_id' => (int) $accountInfo['master_id'],
                        'slave_id' => (int) $accountInfo['slave_id'],
                        'app_id' => (int) $bizunitInfo['appid'],
                        'bizunit_sno' => (int) $bizunitInfo['bizunit-sno'],
                        'transaction_key' => $bizunitInfo['transaction_key'],
                        'message' => $dynamodb->compressWithEncrypt([
                            ['type' => 0, 'level' => 400, 'message' => 'Exceeded the maximum allowed size', 'date' => (new DateTime('now'))->format('U.u')]
                        ]),
                        'reg_date' => date('Y-m-d H:i:s').'.'.CommonUtil::getUsec(),
                        'expire_date' => $expireDate
                    ])
                ]);
            }
            return false;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            return false;
        }
    }
}
