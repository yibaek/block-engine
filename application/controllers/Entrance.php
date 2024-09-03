<?php
namespace controllers;

use DateTime;
use libraries\log\ConsoleLogFileHandler;
use Throwable;
use Exception;
use JsonException;
use ReflectionException;
use RedisClusterException;
use RedisException;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ntuple\Synctree\Exceptions\SAMLException;
use Ntuple\Synctree\Exceptions\OAuthException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Exceptions\SimpleKeyException;
use Ntuple\Synctree\Exceptions\LimitExceededException;
use Ntuple\Synctree\Exceptions\ProductQuotaExceededException;
use Ntuple\Synctree\Exceptions\CommonException;
use Ntuple\Synctree\Log\CreateLogger;
use Ntuple\Synctree\Log\Processor\BizunitProcessor;
use Ntuple\Synctree\Log\LogMessage as PlanLogMessage;
use Ntuple\Synctree\Models\Redis\RedisMgr;
use Ntuple\Synctree\Models\Rdb\RDbManager as PlanMariaDbMgr;
use Ntuple\Synctree\Plan\PlanManager;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Plan\PlanExecutor;
use Ntuple\Synctree\Plan\Stack\StackManager;
use Ntuple\Synctree\Plan\Unit\AccessControler;
use Ntuple\Synctree\Plan\Unit\AccountManager;
use Ntuple\Synctree\Plan\Unit\TransactionManager;
use Ntuple\Synctree\Plan\Unit\ProductControler as PlanProductControler;
use Ntuple\Synctree\Plan\Unit\ProxyManager;
use Ntuple\Synctree\Template\Storage\ExceptionStorage;

use models\rdb\IRdbMgr;
use models\rdb\RDbManager;
use models\dynamo\DynamoDbMgr;
use models\dynamo\DynamoDbHandler;
use libraries\log\LogMessage;
use libraries\log\ConsoleLogger;
use libraries\util\PlanUtil;
use libraries\util\CommonUtil;
use libraries\constant\CommonConst;
use libraries\product\ProductControler;
use libraries\exception\ProductControlException;
use domains\proxy\log\entities\ProxyLog;
use domains\proxy\log\repositories\ISaveProxyLog;

class Entrance
{
    private $ci;

    /**
     * Entrance constructor.
     * @param ContainerInterface $ci
     */
    public function __construct(ContainerInterface $ci)
    {
        $this->ci = $ci;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws Throwable
     * @throws JsonException
     */
    public function index(Request $request, Response $response): Response
    {
        $dynamodb = null;
        $planStorage = null;

        try {
            $redis = $this->ci->get('redis');
            $dynamodb = $this->ci->get('dynamo');

            $studioDb = $this->ci->get('studio_rdb');
            $logDb = (new RDbManager())->getShardMgr($redis, $studioDb, 'log');

            // get plandata and bizunit info
            $planData = $request->getAttribute('planData');
            $bizunitInfo = $request->getAttribute('bizunitInfo');

            // set PlanStorage
            $planStorage = (new PlanStorage())->setOrigin(($request->getAttribute('headers'))->getHeaders(), ($request->getAttribute('params'))->getParam());

            // set PlanManager
            $planManager = (new PlanManager($planStorage))->loadPlan($planData);

            // set PlanStorageUnit with control product
            $this->setPlanStorageUnitWithControlProduct($request, $studioDb, $planStorage, $planManager, $bizunitInfo);

            // connection close
            $studioDb->close();

            // execute plan
            $executor = new PlanExecutor($planStorage, $planManager);
            [$statusCode, $responseHeader, $responseBody] = $executor->execute();

            // set response
            $response = $this->setResponse($request, $response, $planStorage, $statusCode, $responseHeader, $this->makeResponseBody($planStorage, $responseBody));

            return $response;
        } catch (ProductControlException $ex) {
            $response = $this->getResponseWithExceptionResponseCode($response, $ex);
            return $this->setProductControlExceptionResponse($request, $response, $dynamodb, $planStorage, $ex);
        } catch (LimitExceededException | ProductQuotaExceededException $ex) {
            $response = $this->getResponseWithExceptionResponseCode($response, $ex);
            return $this->setRateLimitExceptionResponse($request, $response, $dynamodb, $planStorage);
        } catch (OAuthException | SimpleKeyException | SAMLException $ex) {
            $response = $this->getResponseWithExceptionResponseCode($response, $ex);
            return $this->setAuthorizationExceptionResponse($request, $response, $dynamodb, $planStorage, $ex);
        } catch (SynctreeException $ex) {
            $response = $this->getResponseWithExceptionResponseCode($response, $ex);
            return $this->setSynctreeExceptionResponse($request, $response, $dynamodb, $planStorage, $ex);
        } catch (RedisException | RedisClusterException $ex) {
            LogMessage::exception($ex, 'entrance-redis');
            $response = $this->getResponseWithExceptionResponseCode($response, $ex);
            $responseBody = (new ExceptionStorage($planStorage, new CommonException('Service Temporarily Unavailable')))->getData();
            return $this->setResponse($request, $response, $planStorage, 503, ['application/json'], $this->makeResponseBody($planStorage, $responseBody));
        } catch (Throwable $ex) {
            LogMessage::exception($ex, 'entrance-throwable');
            $response = $this->getResponseWithExceptionResponseCode($response, $ex);
            $responseBody = (new ExceptionStorage($planStorage, new RuntimeException('Bizunit')))->getData();
            return $this->setResponse($request, $response, $planStorage, 400, ['application/json'], $this->makeResponseBody($planStorage, $responseBody));

        // always set console log and proxy log with latest response
        } finally {
            $this->setConsoleLog($dynamodb, $planStorage);
            $this->setRevisionOrProxyLog($request, $response, $planStorage, $studioDb, $logDb);
        }
    }

    /**
     * 발생한 익셉션을 분석해서 적절한 HTTP 상태코드를 Response에 넣어 돌려준다.
     *
     * finally 블록에서의 setProxyLog() 처리를 위해 $response 정리를 추가함
     *
     * @param Response $response
     * @param mixed $ex
     * @return Response
     */
    public function getResponseWithExceptionResponseCode(Response $response, $ex): Response
    {
        $statusCode = 400; // 익셉션이라는 맥락에 의해 기본값은 400

        if ($ex instanceof LimitExceededException || $ex instanceof ProductQuotaExceededException) {
            $statusCode = 429;
        } else if (method_exists($ex, 'getStatus') && $ex->getStatus()) {
            $statusCode = (int) $ex->getStatus();
        } else if (method_exists($ex, 'getStatusCode') && $ex->getStatusCode()) {
            $statusCode = (int) $ex->getStatusCode();
        }

        return $response->withStatus($statusCode);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param PlanStorage $planStorage
     * @param int $statusCode
     * @param array $header
     * @param mixed $body
     * @return Response
     * @throws JsonException
     */
    private function setResponse(Request $request, Response $response, PlanStorage $planStorage, int $statusCode, array $header = [], $body = null): Response
    {
        // make safe header/body
        [$safeHeader, $safeBody] = $this->makeSafeResponseBody($planStorage, $header, $body);

        $response = $response->withStatus($statusCode);
        $response = $this->setResponseHeaders($request, $response, $planStorage, $safeHeader);
        $response->getBody()->write($safeBody);
        return $response;
    }

    /**
     * @param PlanStorage $planStorage
     * @param array $header
     * @param $body
     * @return array
     * @throws JsonException
     */
    private function makeSafeResponseBody(PlanStorage $planStorage, array $header, $body): array
    {
        $isSetContentType = false;
        if (!PlanUtil::isTestMode($planStorage->getOrigin()->getHeaders())) {
            foreach ($header as $key => $value) {
                if ('CONTENT-TYPE' === strtoupper($key)) {
                    $isSetContentType = true;
                    if (false !== strpos($value, 'application/json')) {
                        $body = is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : $body;
                    }
                    break;
                }
            }
        }

        if (!$isSetContentType) {
            if (is_array($body)) {
                $header['Content-Type'] = 'application/json';
                $body = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } else {
                $header['Content-Type'] = 'text/plain';
            }
        }

        return [$header, $body];
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param PlanStorage $planStorage
     * @param array $headers
     * @return Response
     */
    private function setResponseHeaders(Request $request, Response $response, PlanStorage $planStorage, array $headers): Response
    {
        if (!empty($headers)) {
            foreach ($headers as $name => $value) {
                $response = $response->withAddedHeader($name, $value);
            }
        }

        // add default response header
        $response = $response->withAddedHeader(CommonConst::SYNCTREE_TRANSACTION_KEY, $request->getAttribute('transaction_key'));

        // add ratelimit response header
        return $this->addRateLimitResponseHeader($response, $planStorage);
    }

    /**
     * t_api_log 테이블에 entrance 처리("transaction") 경과를 기록한다.
     * * 테스트 모드에서는 작동하지 않음
     * * planStorage의 데이터를 확인해서 적재함
     *
     * @param Request $request
     * @param Response $response
     * @param IRdbMgr $logDb
     * @param PlanStorage $planStorage
     * @throws Exception
     */
    private function transactionMonitor(Request $request, Response $response, IRdbMgr $logDb, PlanStorage $planStorage): void
    {
        if (PlanUtil::isTestMode($planStorage->getOrigin()->getHeaders())) {
            return;
        }

        try {
            $latency = $this->getLatency();
            $size = $this->getSize($response);
            $status_code = $response->getStatusCode();
            $logData = [
                'latency' => $latency,
                'size' => $size,
                'status_code' => $status_code
            ];

            $logDBHandler = $logDb->getHandler();
            $accountInfo = $planStorage->getAccountManager()->getData();
            $bizunitInfo = $planStorage->getTransactionManager()->getData();
            $logDBHandler->executeSetApiLog($accountInfo, $bizunitInfo, $logData);

        } catch (Throwable $ex) {
            LogMessage::exception($ex);
        }
    }

    /**
     * @return float
     */
    private function getLatency(): float
    {
        return (float) (new DateTime('now'))->format('U.u') - $_SERVER['REQUEST_TIME_FLOAT'];
    }

    /**
     * @param Response $response
     * @return int
     */
    private function getSize(Response $response): int
    {
        $contentLength = !empty($_SERVER['CONTENT_LENGTH']) ? $_SERVER['CONTENT_LENGTH'] : 0;
        return $contentLength + $response->getBody()->getSize();
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param DynamoDbMgr $dynamodb
     * @param PlanStorage $planStorage
     * @return Response
     * @throws Throwable
     * @throws JsonException
     */
    private function setRateLimitExceptionResponse(Request $request, Response $response, DynamoDbMgr $dynamodb, PlanStorage $planStorage): Response
    {
        $response = $this->setResponseHeaders($request, $response, $planStorage, []);
        if (PlanUtil::isTestMode($planStorage->getOrigin()->getHeaders())) {
            $response->getBody()->write(json_encode($this->makeResponseBody($planStorage, []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        }

        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param DynamoDbMgr $dynamodb
     * @param PlanStorage $planStorage
     * @param $ex
     * @return Response
     * @throws Throwable
     * @throws JsonException
     */
    private function setAuthorizationExceptionResponse(Request $request, Response $response, DynamoDbMgr $dynamodb, PlanStorage $planStorage, $ex): Response
    {
        $resBody = $ex->getData();
        unset($resBody['status_code']);
        $response = $this->setResponseHeaders($request, $response, $planStorage, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($this->makeResponseBody($planStorage, $resBody), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param DynamoDbMgr $dynamodb
     * @param PlanStorage $planStorage
     * @param $ex
     * @return Response
     * @throws Throwable
     * @throws JsonException
     */
    private function setProductControlExceptionResponse(Request $request, Response $response, DynamoDbMgr $dynamodb, PlanStorage $planStorage, $ex): Response
    {
        $resBody = $ex->getBody();
        unset($resBody['status_code']);

        $response = $this->setResponseHeaders($request, $response, $planStorage, ['Content-Type' => 'application/json']);
        $response->getBody()->write(json_encode($this->makeResponseBody($planStorage, $resBody), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param DynamoDbMgr $dynamodb
     * @param PlanStorage $planStorage
     * @param SynctreeException $ex
     * @return Response
     * @throws JsonException
     * @throws ReflectionException
     * @throws Throwable
     */
    private function setSynctreeExceptionResponse(Request $request, Response $response, DynamoDbMgr $dynamodb, PlanStorage $planStorage, SynctreeException $ex): Response
    {
        $response = $this->setResponseHeaders($request, $response, $planStorage, ['Content-Type' => 'application/json']);

        $exceptionStorage = new ExceptionStorage($planStorage, $ex);
        $responseBody = $this->makeResponseBody($planStorage, $exceptionStorage->getData(), [], [
            'key' => $exceptionStorage->getExceptionKey(),
            'extra' => $exceptionStorage->getExtraData()
        ]);
        $response->getBody()->write(json_encode($responseBody, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response;
    }

    /**
     * @param Response $response
     * @param PlanStorage $planStorage
     * @return Response
     */
    private function addRateLimitResponseHeader(Response $response, PlanStorage $planStorage): Response
    {
        $headers = $planStorage->getAccessControler()->getRateLimitHeader();

        if (!empty($headers)) {
            foreach ($headers as $name => $value) {
                $response = $response->withAddedHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * @param PlanStorage $planStorage
     * @param array $accountInfo
     * @throws Throwable
     */
    private function controlProductThrottle(PlanStorage $planStorage, array $accountInfo): void
    {
        // check test mode
        if (PlanUtil::isTestMode($planStorage->getOrigin()->getHeaders())) {
            return;
        }

        // check division
        if ($accountInfo['master_division'] === 'enterprise') {
            return;
        }

        (new ProductControler($planStorage, $accountInfo))->control();
    }

    /**
     * @param PlanStorage $planStorage
     * @param $responseBody
     * @param array $addMessage
     * @param array $traceData
     * @return mixed
     */
    private function makeResponseBody(PlanStorage $planStorage, $responseBody, array $addMessage = [], array $traceData = [])
    {
        if (PlanUtil::isTestMode($planStorage->getOrigin()->getHeaders())) {
            return [
                'meta' => [
                    'date' => $this->getTransactionDate(),
                    'trace' => $traceData,
                ],
                'response-data' => $responseBody,
                'console-log' => array_merge($planStorage->getLogger()->getMessagePool(), $addMessage)
            ];
        }

        return $responseBody;
    }

    /**
     * @return array
     */
    private function getTransactionDate(): array
    {
        $start = $_SERVER['REQUEST_TIME_FLOAT'];
        $end = (float) (new DateTime('now'))->format('U.u');

        return [
            'start' => $start,
            'end' => $end,
            'latency' => $end - $start
        ];
    }

    /**
     * @param IRdbMgr $studioDb
     * @param PlanStorage $planStorage
     * @param array $bizunitInfo
     * @return array
     * @throws Throwable
     */
    private function getAccountInfo(IRdbMgr $studioDb, PlanStorage $planStorage, array $bizunitInfo): array
    {
        if (PlanUtil::isTestModeForPlayground($planStorage->getOrigin()->getHeaders(), $bizunitInfo)) {
            return $studioDb->getHandler()->executeGetPlaygroundAccountInfo();
        }

        if (PlanUtil::isTestModeForLibrary($planStorage->getOrigin()->getHeaders(), $bizunitInfo)) {
            return $studioDb->getHandler()->executeGetLibraryAccountInfo($planStorage->getTransactionManager()->getBizunitID());
        }

        return $studioDb->getHandler()->executeGetAccountInfo($planStorage->getTransactionManager()->getAppID(), $planStorage->getTransactionManager()->getBizunitID());
    }

    /**
     * proxy_api_log 또는 t_api_log 테이블에 로그 적재한다.
     *
     * @param Request $request
     * @param Response $response
     * @param PlanStorage $planStorage
     * @param IRdbMgr|null $studioDb
     * @param IRdbMgr|null $logDb
     * @return void
     */
    private function setRevisionOrProxyLog(Request $request, Response $response, PlanStorage $planStorage, ?IRdbMgr $studioDb = null, ?IRdbMgr $logDb = null ): void
    {
        try {
            if ($request->getAttribute('isProxy', false) === true) {
                if (!$studioDb) {
                    $studioDb = $this->ci->get('studio_rdb');
                }
                $bizunitInfo = $planStorage->getTransactionManager()->getData();
                $now = date('Y-m-d H:i:s');

                $proxyId = $request->getAttribute('bizunitProxyId', 0);
                if (empty($proxyId)) {
                    $proxyId = $studioDb->getHandler()->executeGetProxyIDByBizunitInfo($bizunitInfo);
                }

                $proxyLog = new ProxyLog;
                $proxyLog->setBizunitProxyId($proxyId);
                $proxyLog->setTransactionKey($bizunitInfo['transaction_key']);
                $proxyLog->setBizunitSno($bizunitInfo['bizunit-sno']);
                $proxyLog->setBizunitId($bizunitInfo['bizunit_id']);
                $proxyLog->setBizunitVersion($bizunitInfo['bizunit_version']);
                $proxyLog->setRevisionSno($bizunitInfo['revision-sno']);
                $proxyLog->setRevisionId($bizunitInfo['revision_id']);
                $proxyLog->setRevisionEnvironment($bizunitInfo['environment']);
                $proxyLog->setLatency($this->getLatency());
                $proxyLog->setSize($this->getSize($response));
                $proxyLog->setResponseStatus($response->getStatusCode());
                $proxyLog->setRegisterDate($now);
                $proxyLog->setTimestampDate($now);

                // add column portalAppID
                if (($authDataMgr=$planStorage->getAuthDataManager()) !== null) {
                    $proxyLog->setPortalAppID($authDataMgr->getVerifyAppID());
                }

                /** @var ISaveProxyLog $proxyLogSaver */
                $proxyLogSaver = $this->ci->get(ISaveProxyLog::class);
                $proxyLogSaver->saveProxyLog($proxyLog);
            } else {
                $this->transactionMonitor($request, $response, $logDb, $planStorage);
            }
        }catch (Exception $ex){
            LogMessage::exception($ex);
        }
    }

    /**
     * 비즈유닛 내 Log 블록에 쌓인 정보들을 모아다가 DynamoDB 또는 Log DB에 적재한다.
     * 테스트 모드에서는 작동하지 않음
     *
     * @param DynamoDbMgr $dynamodb
     * @param PlanStorage $planStorage
     * @param array $addMessage
     * @return bool|null
     * @throws Throwable
     */
    private function setConsoleLog(DynamoDbMgr $dynamodb, PlanStorage $planStorage, array $addMessage = []): ?bool
    {
        if (PlanUtil::isTestMode($planStorage->getOrigin()->getHeaders())) {
            return false;
        }

        // get credential config
        $credential = CommonUtil::getCredentialConfig('console-log');

        // get message
        $message = $planStorage->getLogger()->getMessagePool();
        if (empty($message) && empty($addMessage)) {
            return false;
        }

        // add message
        if (!empty($addMessage)) {
            $message[] = $addMessage;
        }

        $message = $this->filterConsoleLogMessage($message, $credential);
        if (empty($message)) {
            return false;
        }

        // make expire date(add 7day)
        $expireDate = time() + 604800;

        // rdb set console log data
        switch ($credential['load-type']) {
            case 'rdb':
                $redis = $this->ci->get('redis');
                $studioDb = $this->ci->get('studio_rdb');
                
                $logDb = (new RDbManager())->getShardMgr($redis, $studioDb, 'log');

                return $logDb->getHandler()->executeSetConsoleLog(
                    $planStorage->getAccountManager()->getData(),
                    $planStorage->getTransactionManager()->getData(),
                    $message
                );

            case 'elasticsearch':
                if (!$this->ci->has(ConsoleLogFileHandler::class)) {
                    return false;
                }

                /** @var ConsoleLogFileHandler $handler */
                $handler = $this->ci->get(ConsoleLogFileHandler::class);
                return $handler->setConsoleLog(
                    $planStorage->getAccountManager()->getData(),
                    $planStorage->getTransactionManager()->getData(),
                    $message
                );

            default:
                // dynamodb set console log data
                return DynamoDbHandler::setConsoleLog(
                    $dynamodb,
                    $planStorage->getAccountManager()->getData(),
                    $planStorage->getTransactionManager()->getData(),
                    $message,
                    $expireDate
                );
        }
    }

    /**
     * @param Request $request
     * @param IRdbMgr $studioDb
     * @param PlanStorage $planStorage
     * @param PlanManager $planManager
     * @param array $bizunitInfo
     * @throws Throwable
     */
    private function setPlanStorageUnitWithControlProduct(Request $request, IRdbMgr $studioDb, PlanStorage $planStorage, PlanManager $planManager, array $bizunitInfo): void
    {
        // set TransactionManager
        $planStorage->setTransactionManager((new TransactionManager())->setPlanInfo($planManager->getPlanInfo())->setBizunit($bizunitInfo));

        // set Logger
        $planStorage->setLogger(new PlanLogMessage((new CreateLogger())->addProcessor(new BizunitProcessor($planStorage->getTransactionManager()))));

        // set Redis
        $planStorage->setRedisResource(new RedisMgr($planStorage->getLogger()));

        // set Rdb Studio
        $planStorage->setRdbStudioResource((new PlanMariaDbMgr($planStorage->getLogger()))->getRdbMgr('studio'));

        // set AccessControler
        $planStorage->setAccessControler(new AccessControler());

        // get account info
        $accountInfo = $this->getAccountInfo($studioDb, $planStorage, $bizunitInfo);

        // set AccountManager
        $planStorage->setAccountManager((new AccountManager())->parseAccountInfo($accountInfo));

        // set ProductControler
        $planStorage->setProductControler(new PlanProductControler($accountInfo));

        // set StackManager
        $planStorage->setStackManager(new StackManager());

        // set ProxyManager
        if ($request->getAttribute('isProxy', false) === true) {
            $planStorage->setProxyManager($this->getProxyManager($request));
        }

        // control product
        $this->controlProductThrottle($planStorage, $accountInfo);
    }


    /**
     * @param Request $request
     * @return ProxyManager
     */
    private function getProxyManager(Request $request): ProxyManager
    {
        try {
            $path = $request->getAttribute('path', '');
            $method = $request->getAttribute('method', '');
            $requestTime = $request->getAttribute('requestTime', 0);

            return (new ProxyManager())
                ->setPath($path)
                ->setMethod($method)
                ->setRequestTime($requestTime)
                ->setLogMessage([
                    'path' => $path,
                    'method' => $method,
                    'requestTime' => $requestTime
                ]);
        } catch (Exception $ex) {
            return new ProxyManager();
        }
    }

    /**
     * @param array $messages
     * @param array $credential
     * @return array
     */
    private function filterConsoleLogMessage(array $messages, array $credential): array
    {
        if (empty($messages)) {
            return [];
        }

        // check log-level in credential
        $logLevel = $credential['log-level'] ?? 0;
        if (!is_numeric($logLevel) || empty($logLevel)) {
            return $messages;
        }

        $returnMessages = [];
        foreach ($messages as $message) {
            if ($message['level'] < $logLevel) {
                continue;
            }

            $returnMessages[] = $message;
        }

        return $returnMessages;
    }
}
