<?php
namespace libraries\util;

class DynamoDbUtil
{
    /**
     * @param array $bizunitInfo
     * @return string
     */
    public static function makeConsoleLogID(array $bizunitInfo): string
    {
        return $bizunitInfo['bizunit-sno'].'_'.$bizunitInfo['transaction_key'];
    }

    /**
     * @param array $bizunitInfo
     * @return string
     */
    public static function makeConsoleLogKey(array $bizunitInfo): string
    {
        return CommonUtil::getHashKey(implode('_', [$bizunitInfo['bizunit_id'], $bizunitInfo['bizunit_version'], $bizunitInfo['revision-sno']]));
    }
}