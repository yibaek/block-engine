<?php
namespace libraries\util;

use JsonException;
use libraries\constant\CommonConst;
use models\rdb\RDbManager;
use models\s3\S3Mgr;
use Throwable;

class PlanUtil
{
    /**
     * @param array $headers
     * @return int
     */
    public static function extractPlanLoadType(array $headers): int
    {
        // extract plan load type from header
        $data = CommonUtil::intersectHeader($headers, [CommonConst::SYNCTREE_PLAN_LOAD_TYPE => '']);
        if (!empty($data)) {
            return (int)current($data);
        }

        // get credential config
        $credential = CommonUtil::getCredentialConfig('plan');
        return $credential['load-type'];
    }

    /**
     * @param array $headers
     * @return string
     */
    public static function extractPlanID(array $headers): string
    {
        // extract plan id from header
        $data = CommonUtil::intersectHeader($headers, [CommonConst::SYNCTREE_PLAN_ID => '']);
        return !empty($data) ?current($data) :'';
    }

    /**
     * @param array $headers
     * @return string
     */
    public static function extractPlanEnvironment(array $headers): string
    {
        // extract plan environment from header
        $data = CommonUtil::intersectHeader($headers, [CommonConst::SYNCTREE_PLAN_ENVIRONMENT => '']);
        return !empty($data) ?current($data) :'';
    }

    /**
     * @param array $headers
     * @return string
     */
    public static function extractBizunitVersion(array $headers): string
    {
        // extract bizunit version from header
        $data = CommonUtil::intersectHeader($headers, [CommonConst::SYNCTREE_BIZUNIT_VERSION => '']);
        return !empty($data) ?current($data) :'';
    }

    /**
     * @param array $headers
     * @return string
     */
    public static function extractRevisionID(array $headers): string
    {
        // extract revision id from header
        $data = CommonUtil::intersectHeader($headers, [CommonConst::SYNCTREE_REVISION_ID => '']);
        return !empty($data) ?current($data) :'';
    }

    /**
     * @param array $headers
     * @return string
     */
    public static function extractPlanTestMode(array $headers): string
    {
        // extract plan test mode from header
        $data = CommonUtil::intersectHeader($headers, [CommonConst::SYNCTREE_PLAN_TEST_MODE => '']);
        return !empty($data) ?current($data) :'';
    }

    /**
     * @param S3Mgr $storage
     * @param int $loadType
     * @param array $headers
     * @param array $bizunitInfo
     * @return array
     * @throws JsonException
     * @throws Throwable
     */
    public static function getPlanData(S3Mgr $storage, int $loadType, array $headers, array $bizunitInfo): array
    {
        switch ($loadType) {
            case CommonConst::PLAN_DATA_LOAD_TYPE_LOCAL_CONTENTS:
                return json_decode(CommonUtil::getContentsFile(self::makeContentFileName($bizunitInfo)), true, 512, JSON_THROW_ON_ERROR);

            case CommonConst::PLAN_DATA_LOAD_TYPE_S3_CONTENTS:
                return StorageUtil::getPlanData($storage, $headers, $bizunitInfo);

            case CommonConst::PLAN_DATA_LOAD_TYPE_RDB_CONTENTS:
                return self::getPlanDataFromRdb($bizunitInfo);

            default:
                throw new \RuntimeException('invalid plan data load type[type:'.$loadType.']');
        }
    }

    /**
     * @param array $header
     * @return bool
     */
    public static function isTestMode(array $header = []): bool
    {
        return defined('PLAN_MODE')
            && defined('PLAN_MODE_TESTING')
            && PLAN_MODE === PLAN_MODE_TESTING
            && isset($header[strtoupper(CommonConst::SYNCTREE_PLAN_TEST_MODE)]);
    }

    /**
     * @param array $header
     * @param array $bizunitInfo
     * @param bool $isCheckTestMode
     * @return bool
     */
    public static function isTestModeForLibrary(array $header, array $bizunitInfo, bool $isCheckTestMode = true): bool
    {
        $isTestMode = true;
        if ($isCheckTestMode) {
            $isTestMode = self::isTestMode($header);
        }

        return $isTestMode && isset($bizunitInfo['plan-test-mode']) && $bizunitInfo['plan-test-mode'] === CommonConst::PLAN_TEST_MODE_LIBRARY;
    }

    /**
     * @param array $header
     * @param array $bizunitInfo
     * @param bool $isCheckTestMode
     * @return bool
     */
    public static function isTestModeForPlayground(array $header, array $bizunitInfo, bool $isCheckTestMode = true): bool
    {
        $isTestMode = true;
        if ($isCheckTestMode) {
            $isTestMode = self::isTestMode($header);
        }

        return $isTestMode && isset($bizunitInfo['plan-test-mode']) && $bizunitInfo['plan-environment'] === 'playground';
    }

    /**
     * @param array $bizunitInfo
     * @return string
     */
    private static function makeContentFileName(array $bizunitInfo): string
    {
        $file = $bizunitInfo['plan-id'];

        if (!empty($bizunitInfo['bizunit-version'])) {
            $file .= '.'.$bizunitInfo['bizunit-version'];
        }

        $revisionID = $bizunitInfo['revision-id'] ?? '';
        if (!empty($revisionID)) {
            $file .= '.'.$revisionID;
        }

        return $file.'.json';
    }

    /**
     * @param array $bizunitInfo
     * @return mixed
     * @throws JsonException
     * @throws Throwable
     */
    private static function getPlanDataFromRdb(array $bizunitInfo)
    {
        $planRdb = (new RDbManager())->getRdbMgr('plan');
        $planData = $planRdb->getHandler()->executeGetPlan($bizunitInfo['plan-environment'], $bizunitInfo['plan-id'], $bizunitInfo['bizunit-version'], $bizunitInfo['revision-id'] ?? '');

        // connection close
        $planRdb->close();
        
        return json_decode($planData['plan_content'], true, 512, JSON_THROW_ON_ERROR);
    }
}