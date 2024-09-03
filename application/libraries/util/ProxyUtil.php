<?php
namespace libraries\util;

use DateTime;
use Throwable;
use JsonException;
use Psr\Http\Message\ServerRequestInterface as Request;

use models\rdb\IRdbMgr;
use models\rdb\RDbManager;
use models\rdb\NotFoundBizunit;
use libraries\constant\CommonConst;

class ProxyUtil
{
    private const ENV_PRODUCTION = 'production';

    /** @var IRdbMgr $studioDb */
    private $studioDb;

    /**
     * ProxyUtil constructor.
     * @param IRdbMgr $studioDb
     * @since SYN-683
     */
    public function __construct(IRdbMgr $studioDb)
    {
        $this->studioDb = $studioDb;
    }

    /**
     * 주어진 요청객체를 '프록시를 통한 요청'의 요청객체로 만든다.
     *
     * @param Request $request
     * @return Request
     * @throws JsonException
     * @throws Throwable
     */
    public function getProxyRequest(Request $request): Request
    {
        // get Request Parameters
        $params = $this->getParamsData($request);

        // init Request QueryString Proxy Data
        $request = $this->setClearProxyDataRequestQueryString($request);

        // get Data MasterAccountNo
        $masterAccountNo = $this->getMasterAccount($params['host']);

        // get Data Proxy-Base-Path
        $path = $this->getConvertPath($params['path']);

        // get request method
        $method = $request->getMethod();

        // get Proxy-Base-Path Mapping BizUnit Info
        $dataBizunit = $this->getProxyMappingBizUnitInfo($this->studioDb, $masterAccountNo, $path, $method);

        // set Inject Http-header
        $request = $this->setPlanDataHeader($request, $dataBizunit['bizunit_id'], self::ENV_PRODUCTION, $dataBizunit['bizunit_version'], $dataBizunit['revision_id']);

        // set flag and attributes
        $request = $this->setProxyDataAttribute($request, $masterAccountNo, $path, $method, (int)$dataBizunit['bizunit_proxy_id']);

        return $request;
    }

    /**
     * @param string $path
     * @return string
     */
    private function getConvertPath(string $path): string
    {
        $path = str_replace('//', '/', $path);
        if ($path[0] !== '/') {
            $path = sprintf("/%s", $path);
        }
        return $path;
    }

    /**
     * @param IRdbMgr $rdb
     * @param string $masterAccountNo
     * @param string $path
     * @param string $method
     * @return array|string[]
     * @throws NotFoundBizunit
     */
    private function getProxyMappingBizUnitInfo(IRdbMgr $rdb, string $masterAccountNo, string $path, string $method): array
    {
        $data = $rdb->getHandler()->executeGetBizUnitProxyInfo($path, $method, $masterAccountNo);

        if (!empty($data)) {
            return [
                'bizunit_proxy_id' => $data['bizunit_proxy_id'] ?? '',
                'bizunit_id' => $data['bizunit_id'] ?? '',
                'bizunit_version' => $data['bizunit_version'] ?? '',
                'revision_id' => $data['revision_id'] ?? '',
            ];
        }

        return [
            'bizunit_proxy_id' => '',
            'bizunit_id' => '',
            'bizunit_version' => '',
            'revision_id' => '',
        ];
    }

    /**
     * @param string $host
     * @return string
     */
    private function getMasterAccount(string $host): string
    {
        // get credential config
        $credential = CommonUtil::getCredentialConfig('provider');

        if ($credential['provider'] === 'ntuple') {
            $explodeHost = explode('.', $host);
            $masterAccountNo = $explodeHost[0];
        } else {
            $masterAccountNo = '-1';
        }

        return $masterAccountNo;
    }

    /**
     * @param Request $request
     * @return array
     * @throws JsonException
     */
    private function getParamsData(Request $request): array
    {
        return $request->getQueryParams();
    }

    /**
     * plan을 execute하기 위한 Plan 자료들을 요청헤더 스푸핑 형식으로써 주입한다.
     *
     * @param Request $request
     * @param string $planId
     * @param string $planEnv
     * @param string $bizunitVersion
     * @param string $revisionId
     * @return Request
     */
    private function setPlanDataHeader(Request $request, string $planId, string $planEnv, string $bizunitVersion, string $revisionId): Request
    {
        $headers = [
            CommonConst::SYNCTREE_PLAN_ID          => $planId,
            CommonConst::SYNCTREE_PLAN_ENVIRONMENT => $planEnv,
            CommonConst::SYNCTREE_BIZUNIT_VERSION  => $bizunitVersion,
            CommonConst::SYNCTREE_REVISION_ID      => $revisionId,
        ];
        foreach ($headers as $str => $value) {
            $request = $request->withHeader($this->convertHeaderKey($str), $value);
        }
        return $request;
    }

    /**
     * proxy 사용 이력을 기록하기 위한 Proxy 정보를 요청객체 속성으로써 주입한다.
     *
     * @param Request $request
     * @param string $masterAccountNo
     * @param string $path
     * @param string $method
     * @param int $bizunitProxyId
     * @return Request
     */
    private function setProxyDataAttribute(Request $request, string $masterAccountNo, string $path, string $method, int $bizunitProxyId): Request
    {
        $requestTime = (new DateTime('now'))->format('U.u');
        $attrs = [
            'isProxy' => true,
            'masterAccountNo' => $masterAccountNo,
            'path' => $path,
            'method' => $method,
            'requestTime' => $requestTime,
            'bizunitProxyId' => $bizunitProxyId,
        ];
        foreach ($attrs as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }
        return $request;
    }

    /**
     * @param string $str
     * @return string
     */
    private function convertHeaderKey(string $str): string
    {
        return strtoupper(str_replace('-', '_', $str));
    }

    /**
     * @param Request $request
     * @return Request
     */
    private function setClearProxyDataRequestQueryString(Request $request): Request
    {
        $params = $request->getQueryParams();
        unset($params['path'], $params['query'], $params['host']);

        return $request->withQueryParams($params);
    }
}