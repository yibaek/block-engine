<?php
namespace Ntuple\Synctree\Protocol\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JsonException;
use libraries\constant\CommonConst;
use Ntuple\Synctree\Constant\PlanConst;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Models\Redis\RedisKeys;
use Ntuple\Synctree\Models\Redis\RedisMgr;
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\RedisUtil;
use Throwable;

class HttpExecutor
{
    public const HTTP_METHOD_GET = 'get';
    public const HTTP_METHOD_POST = 'post';
    public const HTTP_METHOD_PUT = 'put';
    public const HTTP_METHOD_DELETE = 'delete';
    public const HTTP_METHOD_PATCH = 'patch';
    public const HTTP_METHOD_SECURE = 'secure';

    private $logger;
    private $client;
    private $endPoint;
    private $options;
    private $method;
    private $headers;
    private $bodys;
    private $isConvertJson;
    private $isThrowException;
    private $secureVerificationCode;
    private $repository;

    /**
     * HttpExecutor constructor.
     * @param LogMessage $logger
     * @param HandlerStack|null $handler
     */
    public function __construct(LogMessage $logger, HandlerStack $handler = null)
    {
        $this->logger = $logger;
        $this->options = [];
        $this->headers = [];
        $this->bodys = [];
        $this->isConvertJson = false;
        $this->isThrowException = false;

        // initialize the client with the handler option
        $this->client = new Client([
            'handler' => $handler
        ]);
    }

    /**
     * @param string $endPoint
     * @return HttpExecutor
     */
    public function setEndPoint(string $endPoint): HttpExecutor
    {
        $this->endPoint = $endPoint;
        return $this;
    }

    /**
     * @param array $options
     * @return HttpExecutor
     */
    public function setOptions(array $options): HttpExecutor
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @param string $method
     * @return HttpExecutor
     */
    public function setMethod(string $method): HttpExecutor
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @param array $headers
     * @return HttpExecutor
     */
    public function setHeaders(array $headers): HttpExecutor
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @param $bodys
     * @return HttpExecutor
     */
    public function setBodys($bodys): HttpExecutor
    {
        $this->bodys = $bodys;
        return $this;
    }

    /**
     * @param bool $isConvertJson
     * @return HttpExecutor
     */
    public function isConvertJson(bool $isConvertJson): HttpExecutor
    {
        $this->isConvertJson = $isConvertJson;
        return $this;
    }

    /**
     * @param bool $isThrowException
     * @return HttpExecutor
     */
    public function isThrowException(bool $isThrowException): HttpExecutor
    {
        $this->isThrowException = $isThrowException;
        return $this;
    }

    /**
     * @param RedisMgr $repository
     * @return HttpExecutor
     */
    public function setRepository($repository): HttpExecutor
    {
        $this->repository = $repository;
        return $this;
    }

    /**
     * @param string $code
     * @return HttpExecutor
     */
    public function setSecureVerificationCode(string $code): HttpExecutor
    {
        $this->secureVerificationCode = $code;
        return $this;
    }

    /**
     * @return array
     * @throws GuzzleException
     * @throws Throwable
     */
    public function execute(): array
    {
        try {
            switch ($this->method) {
                case self::HTTP_METHOD_GET:
                    return $this->get();

                case self::HTTP_METHOD_POST:
                    return $this->post();

                case self::HTTP_METHOD_PUT:
                    return $this->put();

                case self::HTTP_METHOD_DELETE:
                    return $this->delete();

                case self::HTTP_METHOD_PATCH:
                    return $this->patch();

                case self::HTTP_METHOD_SECURE:
                    return $this->secure();

                default:
                    throw new \RuntimeException('invalid http method[method:'.$this->method.']');
            }
        } catch (Throwable $ex) {
            $this->logger->exception($ex, 'method:'.$this->method.', end-point:'.$this->endPoint.', options:'. json_encode($this->options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE, 512));
            throw $ex;
        }
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    private function post(): array
    {
        // set options
        $options = $this->setDefaultOption();

        // add header option
        $options['headers'] = $this->headers;

        // add body option
        $this->addBodyOptionForPost($options);

        // call request
        $response = $this->client->request('POST', $this->endPoint, $options);

        // return response after make response data
        return $this->makeResponse($response);
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    private function put(): array
    {
        // set options
        $options = $this->setDefaultOption();

        // add header option
        $options['headers'] = $this->headers;

        // add body option
        $this->addBodyOptionForPost($options);

        // call request
        $response = $this->client->request('PUT', $this->endPoint, $options);

        // return response after make response data
        return $this->makeResponse($response);
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    private function delete(): array
    {
        // set options
        $options = $this->setDefaultOption();

        // add header option
        $options['headers'] = $this->headers;

        // add body option
        if (!empty($this->bodys)) {
            $options['query'] = $this->bodys;
        }

        // call request
        $response = $this->client->request('DELETE', $this->endPoint, $options);

        // return response after make response data
        return $this->makeResponse($response);
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    private function patch(): array
    {
        // set options
        $options = $this->setDefaultOption();

        // add header option
        $options['headers'] = $this->headers;

        // add body option
        $this->addBodyOptionForPost($options);

        // call request
        $response = $this->client->request('PATCH', $this->endPoint, $options);

        // return response after make response data
        return $this->makeResponse($response);
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    private function get(): array
    {
        // set options
        $options = $this->setDefaultOption();

        // add header option
        $options['headers'] = $this->headers;

        // add body option
        if (!empty($this->bodys)) {
            $options['query'] = $this->bodys;
        }

        // call request
        $response = $this->client->request('GET', $this->endPoint, $options);

        // return response after make response data
        return $this->makeResponse($response);
    }

    /**
     * @return array
     * @throws GuzzleException
     * @throws Throwable
     */
    private function secure(): array
    {
        // get secure protocol key
        $secureKey = RedisKeys::makeSecureProtocolKey();

        // set data to redis
        RedisUtil::setDataWithExpire(
            $this->repository,
            $secureKey,
            CommonConst::SECURE_PROTOCOL_REDIS_DB,
            CommonConst::SECURE_PROTOCOL_REDIS_SESSION_EXPIRE_TIME,
            $this->bodys);

        // rebuild header and body for secure protocol
        $this->headers[PlanConst::SYNCTREE_SECURE_KEY] = $secureKey;
        $this->headers[PlanConst::SYNCTREE_VERIFICATION_CODE] = $this->secureVerificationCode;
        $this->bodys = [];

        // call post
        return $this->post();
    }

    /**
     * @param Response $response
     * @return array
     */
    private function makeResponse(Response $response): array
    {
        // get response body
        $responseBody = (string)$response->getBody();

        try {
            if (true === $this->isConvertJson) {
                $responseBody = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (JsonException $ex) {
        }

        return [
            $response->getStatusCode(),
            $response->getHeaders(),
            $responseBody
        ];
    }

    /**
     * @param array $options
     */
    private function addBodyOptionForPost(array &$options): void
    {
        if (true === CommonUtil::isSetJsonContentType($this->headers)) {
            $options['json'] = $this->bodys;
            return;
        }

        if (is_array($this->bodys) && true === CommonUtil::isSetUrlEncodedContentType($this->headers)) {
            $options['form_params'] = $this->bodys;
        } else {
            $options['body'] = $this->bodys;
        }
    }

    /**
     * @return array
     */
    private function setDefaultOption(): array
    {
        // set default option
        $options = [
            'http_errors' => $this->isThrowException
        ];

        return array_merge($options, $this->options);
    }
}