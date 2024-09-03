<?php
namespace libraries\util;

use models\s3\S3Mgr;
use Throwable;

class StorageUtil
{
    /**
     * @param S3Mgr $storage
     * @param array $headers
     * @param array $bizunitInfo
     * @return array
     * @throws Throwable
     */
    public static function getPlanData(S3Mgr $storage, array $headers, array $bizunitInfo): array
    {
        // get object
        $prefix = 'plan-data/'.self::getEnvironment().'/'.self::getRevisionEnvironment($headers, $bizunitInfo).'/'.$bizunitInfo['plan-id'].'.'.$bizunitInfo['bizunit-version'];

        $revisionID = $bizunitInfo['revision-id'] ?? '';
        if (!empty($revisionID)) {
            $prefix .= '.' . $revisionID;
        }

        $prefix .= '.json';
        $planData = $storage->getObject(self::getBucketContents(), $prefix, true);
        if (empty($planData)) {
            throw new \RuntimeException('failed to get plan data[prefix:'.$prefix.']');
        }

        return $planData;
    }

    /**
     * @return string
     */
    private static function getEnvironment(): string
    {
        switch (APP_ENV) {
            case APP_ENV_PRODUCTION:
                return 'production';

            case APP_ENV_STAGE:
                return 'stage';

            default:
                return 'develop';
        }
    }

    /**
     * @param array $headers
     * @param array $bizunitInfo
     * @return string
     */
    private static function getRevisionEnvironment(array $headers, array $bizunitInfo): string
    {
        if (PlanUtil::isTestModeForLibrary($headers, $bizunitInfo)) {
            return 'testing';
        }

        return $bizunitInfo['plan-environment'];
    }

    /**
     * @return string
     */
    private static function getBucketContents(): string
    {
        // load credential
        $credential = CommonUtil::getCredentialConfig('s3');

        return $credential['bucket-contents'];
    }
}