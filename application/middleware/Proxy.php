<?php
namespace middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Exception;
use Throwable;

use libraries\log\LogMessage;
use libraries\util\ProxyUtil;
use libraries\util\CommonUtil;
use models\rdb\IRdbMgr;
use models\rdb\NotFoundBizunit;

class Proxy
{
    /** @var IRdbMgr $studioDb */
    private $studioDb;

    /**
     * SetPlanData constructor.
     * @param IRdbMgr $studioDb
     */
    public function __construct(IRdbMgr $studioDb)
    {
        $this->studioDb = $studioDb;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     * @throws Exception
     * @throws Throwable
     */
    public function __invoke(Request $request, Response $response, callable $next): Response
    {
        try {
            // set proxy info
            $request = (new ProxyUtil($this->studioDb))->getProxyRequest($request);
            return $next($request, $response);

        } catch (NotFoundBizunit $ex) {
            LogMessage::exception($ex, 'proxy-throwable-not-found-bizunit');

            // ntuple 이라면 기존 것 사용
            if ($this->isProviderNtuple()) {
                $response = $response->withStatus(400);
                $response->getBody()->write($ex->getMessage());
            } else {
                $response = $response->withStatus(404);
                $response = $response->withHeader('Content-Type', 'application/json');
                $body = [
                    'rsp_code' => 40401,
                    'rsp_msg' => 'Entry point does not exist.',
                    // 'rsp_msg' => $ex->getMessage(),
                ];
                $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
            }
            return $response;

        } catch (Throwable $ex) {
            LogMessage::exception($ex, 'proxy-throwable');

            // ntuple 이라면 기존 것 사용
            if ($this->isProviderNtuple()) {
                $response = $response->withStatus(400);
                $response->getBody()->write($ex->getMessage());
                return $response;
            }

            // 아니라면 mydata 규격 사용 refs #5514
            throw $ex;
        }
    }

    /**
     * @return bool 크리덴셜의 provider 가 설정돼 있지 않거나 ntuple 로 설정돼 있을 때 true
     */
    protected function isProviderNtuple(): bool
    {
        $credential = CommonUtil::getCredentialConfig('provider');
        $provider = (!empty($credential['provider']) ? $credential['provider'] : 'ntuple');

        return $provider === 'ntuple';
    }
}
