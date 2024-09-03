<?php
namespace controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use RuntimeException;
use JsonException;
use Exception;
use Throwable;

use models\redis\RedisMgr;
use libraries\log\LogMessage;
use libraries\util\RedisUtil;
use libraries\util\CommonUtil;
use libraries\constant\CommonConst;

class SecureProtocol
{
    private $ci;

    /**
     * SecureProtocol constructor.
     * @param ContainerInterface $ci
     */
    public function __construct(ContainerInterface $ci)
    {
        $this->ci = $ci;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     * @throws JsonException
     */
    public function getCommand(Request $request, Response $response): ?Response
    {
        try {
            $redis = new RedisMgr();

            // get secure key
            $secureKey = $this->getSecureKeyFromHeader($request);

            // get data with delete key
            if (false === ($sessionData=RedisUtil::getDataWithDel($redis, $secureKey, CommonConst::SECURE_PROTOCOL_REDIS_DB))) {
                throw new RuntimeException('failed to get secure protocol session data[key:'.$secureKey.']');
            }

            $response->getBody()->write(json_encode($sessionData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            return $response;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @return string
     * @throws Exception
     */
    private function getSecureKeyFromHeader(Request $request): string
    {
        $secureKey = CommonUtil::intersectHeader(($request->getAttribute('headers'))->getHeaders(), [CommonConst::SYNCTREE_SECURE_KEY => null]);
        if (empty($secureKey)) {
            throw new RuntimeException('failed to get synctree secure protocol key from http header');
        }

        return current($secureKey);
    }
}