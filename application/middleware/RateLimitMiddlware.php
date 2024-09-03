<?php
namespace middleware;

use libraries\util\CommonUtil;
use Throwable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Models\Redis\RedisMgr;
use Ntuple\Synctree\Util\AccessControl\RateLimit\Rate;
use Ntuple\Synctree\Util\AccessControl\RateLimit\RateLimit;
use Ntuple\Synctree\Util\AccessControl\Exception\LimitExceededException;

use models\rdb\RDbManager;
use models\rdb\IRdbMgr;
use middleware\exceptions\RateLimitExceededException;
use middleware\exceptions\UnknownRateLimitMiddlewareException;

/**
 * 유량 제어 미들웨어
 * Portal 에 공개되어있는 API(Proxy, Bizunit)에 유량 제어를 처리한다
 */
class RateLimitMiddlware
{
    /** @var IRdbMgr $studioDb */
    private $studioDb;

    /**
     * RateLimitMiddlware constructor.
     * @param IRdbMgr $studioDb
     * @since SYN-683
     */
    public function __construct(IRdbMgr $studioDb)
    {
        $this->studioDb = $studioDb;
    }

    /**
     * @param string $path
     * @return string
     */
    protected function getConvertPath(string $path): string
    {
        $path = str_replace('//', '/', $path);
        if ($path[0] !== '/') {
            $path = sprintf("/%s", $path);
        }
        return $path;
    }

    /**
     * @param string $masterAccountNo
     * @param string $path
     * @param string $method
     * @return integer|null
     */
    protected function loadProxyId(string $masterAccountNo, string $path, string $method): ?int
    {
        $this->studioDb->makeConnection();
        $query = $this->studioDb->getSelect()
            ->select('bizunit_proxy_id')
            ->table('bizunit_proxy')
            ->where('bizunit_proxy_base_path', $path)
            ->whereAnd('bizunit_proxy_method', $method);

        if ($masterAccountNo !== '-1') {
            $query->whereAnd('master_account', $masterAccountNo);
        }

        $resData = $this->studioDb->executeQuery($query);

        return is_array($resData) && $resData !== [] ? (int) $resData[0]['bizunit_proxy_id'] : null;
    }

    /**
     * @param int $proxy_id
     * @return array|null
     */
    protected function loadApiRateLimit(int $proxy_id): ?array
    {
        $portalRdb = (new RDbManager())->getRdbMgr('portal');
        $portalRdb->makeConnection();
        $resData = $portalRdb->executeQuery(
            $portalRdb->getSelect()
                ->select('limit_rate_count')
                ->select('limit_rate_period')
                ->table('proxy_api_limit')
                ->where('bizunit_proxy_id', $proxy_id)
        );
        $portalRdb->close();

        return is_array($resData) && $resData !== [] ? reset($resData) : null;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     */
    public function __invoke(Request $request, Response $response, callable $next): Response
    {
        try {
            $redisMgr = new RedisMgr(new LogMessage());
            $group  = "global-access-control-ratelimit";

            $params = $request->getQueryParams();
            $path = $this->getConvertPath($params['path']);
            $method = $request->getMethod();
            $host = $params['host'];

            // get Data MasterAccountNo
            $masterAccountNo = $this->getMasterAccount($host);

            $proxy_id = $this->loadProxyId($masterAccountNo, $path, $method);
            if (!$proxy_id) {
                return $next($request, $response);
            }

            $api_rate_limit = $this->loadApiRateLimit($proxy_id);
            if (!$api_rate_limit) {
                return $next($request, $response);
            }

            $limit = (int) $api_rate_limit['limit_rate_count'];
            if ($limit === -1) {
                // -1 의 의미는 무제한을 뜻함
                return $next($request, $response);
            }

            $key = hash('sha256', trim($path ."-". $method . "-" . $host), false);
            $interval = (int) $api_rate_limit['limit_rate_period'];
            $rate = (new Rate())->perCustom($limit, $interval);

            $rl = new RateLimit($redisMgr, $group);
            $rl->limit($key, $rate);

            // call next middleware or application
            return $next($request, $response);
        } catch (LimitExceededException $e) {
            throw new RateLimitExceededException();
        } catch (Throwable $t) {
            throw new UnknownRateLimitMiddlewareException($t);
        }
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
}