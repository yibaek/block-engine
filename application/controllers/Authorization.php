<?php
namespace controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use RuntimeException;
use JsonException;
use Throwable;

use models\rdb\RDbManager;
use libraries\log\LogMessage;
use libraries\util\CommonUtil;
use libraries\auth\saml\Saml2;
use libraries\auth\oauth\OAuth2;
use libraries\auth\simplekey\SimpleKey;
use libraries\auth\AuthorizationManager;
use libraries\auth\AuthorizationClientManager;
use libraries\auth\AuthorizationResponseManager;
use libraries\constant\AuthorizationConst;
use libraries\exception\AuthorizationInvalidException;

class Authorization
{
    private $ci;

    /**
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
     */
    public function joinCredential(Request $request, Response $response): ?Response
    {
        try {
            $authRdb = (new RDbManager())->getRdbMgr('auth');
            $params = ($request->getAttribute('params'))->getParam();

            // validate required param
            [$isSuccess, $message] = CommonUtil::validateParams($params, ['appid', 'credential_id']);
            if (false === $isSuccess) {
                return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD))->makeResult($message));
            }

            // add credential
            $credentialMatchID = $authRdb->getHandler()->executeAddCredential($params['appid'], $params['credential_id']);

            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_SUCCESS, ['credential_match_id' => $credentialMatchID]))->makeResult());
        } catch (RuntimeException $ex) {
            LogMessage::exception($ex);
            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAILURE))->makeResult($ex->getMessage()));
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function reJoinCredential(Request $request, Response $response): ?Response
    {
        try {
            $authRdb = (new RDbManager())->getRdbMgr('auth');
            $params = ($request->getAttribute('params'))->getParam();

            // validate required param
            [$isSuccess, $message] = CommonUtil::validateParams($params, ['appid', 'credential_id', 'previous_credential_id']);
            if (false === $isSuccess) {
                return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD))->makeResult($message));
            }

            // delete credential
            $authRdb->getHandler()->executeDeleteCredential($params['appid'], $params['previous_credential_id']);

            // add credential
            $credentialMatchID = $authRdb->getHandler()->executeAddCredential($params['appid'], $params['credential_id']);

            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_SUCCESS, ['credential_match_id' => $credentialMatchID]))->makeResult());
        } catch (RuntimeException $ex) {
            LogMessage::exception($ex);
            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAILURE))->makeResult($ex->getMessage()));
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     * @throws JsonException
     */
    public function deleteJoinCredential(Request $request, Response $response): ?Response
    {
        try {
            $authRdb = (new RDbManager())->getRdbMgr('auth');
            $params = ($request->getAttribute('params'))->getParam();

            // validate required param
            [$isSuccess, $message] = CommonUtil::validateParams($params, ['appid', 'credential_id']);
            if (false === $isSuccess) {
                return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD))->makeResult($message));
            }

            // delete credential
            $authRdb->getHandler()->executeDeleteCredential($params['appid'], $params['credential_id']);

            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_SUCCESS))->makeResult());
        } catch (RuntimeException $ex) {
            LogMessage::exception($ex);
            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAILURE))->makeResult($ex->getMessage()));
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function generateOauth2Key(Request $request, Response $response): ?Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            // validate required param
            [$isSuccess, $message] = CommonUtil::validateParams($params, ['credential_id', 'environment']);
            if (false === $isSuccess) {
                return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD))->makeResult($message));
            }

            // get authorization manager
            $authManager = new AuthorizationManager(AuthorizationClientManager::createFromParam(OAuth2::AUTHORIZATION_TYPE, $params));

            // validate envirionment ..
            if (false === $authManager->isRequiredValid()) {
                return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD))->makeResult('invalid required field'));
            }

            return $this->makeResponse($response, $authManager->generateKey());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function generateSimpleKey(Request $request, Response $response): ?Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            // validate required param
            [$isSuccess, $message] = CommonUtil::validateParams($params, ['credential_id', 'environment']);
            if (false === $isSuccess) {
                return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD))->makeResult($message));
            }

            // get authorization manager
            $authManager = new AuthorizationManager(AuthorizationClientManager::createFromParam(SimpleKey::AUTHORIZATION_TYPE, $params));

            // validate envirionment ..
            if (false === $authManager->isRequiredValid()) {
                return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD))->makeResult('invalid required field'));
            }

            return $this->makeResponse($response, $authManager->generateKey());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function deleteOauth2Key(Request $request, Response $response): ?Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            // validate required param
            [$isSuccess, $message] = CommonUtil::validateParams($params, ['credential_id', 'environment', 'client_id']);
            if (false === $isSuccess) {
                return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD))->makeResult($message));
            }

            // get authorization manager
            $authManager = new AuthorizationManager(AuthorizationClientManager::createFromParam(OAuth2::AUTHORIZATION_TYPE, $params));

            // validate envirionment ..
            if (false === $authManager->isRequiredValid()) {
                return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD))->makeResult('invalid required field'));
            }

            return $this->makeResponse($response, $authManager->deleteKey());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function deleteSimpleKey(Request $request, Response $response): ?Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            // validate required param
            [$isSuccess, $message] = CommonUtil::validateParams($params, ['credential_id', 'environment', 'key']);
            if (false === $isSuccess) {
                return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD))->makeResult($message));
            }

            // get authorization manager
            $authManager = new AuthorizationManager(AuthorizationClientManager::createFromParam(SimpleKey::AUTHORIZATION_TYPE, $params));

            // validate envirionment ..
            if (false === $authManager->isRequiredValid()) {
                return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD))->makeResult('invalid required field'));
            }

            return $this->makeResponse($response, $authManager->deleteKey());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function authorizeOauth2(Request $request, Response $response): ?Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            [$statusCode, $resHeader, $resBody] = (new AuthorizationManager(AuthorizationClientManager::createFromParam(OAuth2::AUTHORIZATION_TYPE, $params), $params['config'] ?? []))->authorize();
            return $this->makeResponse($response, $resBody, [], $statusCode);
        } catch (AuthorizationInvalidException $ex) {
            LogMessage::exception($ex);
            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_VALIDATE_TOKEN, $ex->getBody()))->makeResult(), [], $ex->getStatus());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function generateOauth2Token(Request $request, Response $response): ?Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            [$statusCode, $resHeader, $resBody] = (new AuthorizationManager(AuthorizationClientManager::createFromParam(OAuth2::AUTHORIZATION_TYPE, $params), $params['config'] ?? []))->generateToken();
            return $this->makeResponse($response, $resBody, [], $statusCode);
        } catch (AuthorizationInvalidException $ex) {
            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_GENERATE_TOKEN, $ex->getBody()))->makeResult(), [], $ex->getStatus());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function validateOauth2Token(Request $request, Response $response): ?Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            [$statusCode, $resHeader, $resBody] = (new AuthorizationManager(AuthorizationClientManager::createFromParam(OAuth2::AUTHORIZATION_TYPE, $params), $params['config'] ?? []))->validation();
            return $this->makeResponse($response, $resBody, [], $statusCode);
        } catch (AuthorizationInvalidException $ex) {
            LogMessage::exception($ex);
            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_VALIDATE_TOKEN, $ex->getBody()))->makeResult(), [], $ex->getStatus());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function revokeOauth2Token(Request $request, Response $response): ?Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            [$statusCode, $resHeader, $resBody] = (new AuthorizationManager(AuthorizationClientManager::createFromParam(OAuth2::AUTHORIZATION_TYPE, $params), $params['config'] ?? []))->revokeToken();
            return $this->makeResponse($response, $resBody, [], $statusCode);
        } catch (AuthorizationInvalidException $ex) {
            LogMessage::exception($ex);
            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_GENERATE_TOKEN, $ex->getBody()))->makeResult(), [], $ex->getStatus());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function validateSimpleKey(Request $request, Response $response): ?Response
    {
        try {
            $params = array_merge(($request->getAttribute('params'))->getParam(), [
                'key' => AuthorizationClientManager::getSimpleKeyInHeader(($request->getAttribute('headers'))->getHeaders())
            ]);

            [$statusCode, $resHeader, $resBody] = (new AuthorizationManager(AuthorizationClientManager::createFromParam(SimpleKey::AUTHORIZATION_TYPE, $params)))->validation();
            return $this->makeResponse($response, $resBody, [], $statusCode);
        } catch (AuthorizationInvalidException $ex) {
            LogMessage::exception($ex);
            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_VALIDATE_TOKEN, $ex->getBody()))->makeResult(), [], $ex->getStatus());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function generateSaml2AuthnRequest(Request $request, Response $response): ?Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            [$statusCode, $resHeader, $resBody] = (new AuthorizationManager(AuthorizationClientManager::createFromParam(Saml2::AUTHORIZATION_TYPE, $params), $params['config'] ?? []))->generateAuthnRequest();
            return $this->makeResponse($response, $resBody, [], $statusCode);
        } catch (AuthorizationInvalidException $ex) {
            LogMessage::exception($ex);
            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_GENERATE_TOKEN, $ex->getBody()))->makeResult(), [], $ex->getStatus());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     */
    public function generateSaml2Assertion(Request $request, Response $response): ?Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            [$statusCode, $resHeader, $resBody] = (new AuthorizationManager(AuthorizationClientManager::createFromParam(Saml2::AUTHORIZATION_TYPE, $params), $params['config'] ?? []))->generateAssertion();
            return $this->makeResponse($response, $resBody, [], $statusCode);
        } catch (AuthorizationInvalidException $ex) {
            LogMessage::exception($ex);
            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_GENERATE_TOKEN, $ex->getBody()))->makeResult(), [], $ex->getStatus());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|null
     * @throws Throwable
     * @throws \JsonException
     */
    public function validateSaml2Assertion(Request $request, Response $response): ?Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            [$statusCode, $resHeader, $resBody] = (new AuthorizationManager(AuthorizationClientManager::createFromParam(Saml2::AUTHORIZATION_TYPE, $params), $params['config'] ?? []))->validateAssertion();
            return $this->makeResponse($response, $resBody, [], $statusCode);
        } catch (AuthorizationInvalidException $ex) {
            LogMessage::exception($ex);
            return $this->makeResponse($response, (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_GENERATE_TOKEN, $ex->getBody()))->makeResult(), [], $ex->getStatus());
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param Response $response
     * @param array|string $body
     * @param array $header
     * @param int $statusCode
     * @param string $headerType
     * @return Response
     */
    private function makeResponse(Response $response, $body, array $header = [], int $statusCode = 200, string $headerType = 'json'): Response
    {
        if ('json' === $headerType) {
            $response = CommonUtil::responseWithJson($response);
        }

        $response = $response->withStatus($statusCode);
        $response = $this->setResponseHeaders($response, $header);

        $response->getBody()->write($body);
        return $response;
    }

    /**
     * @param Response $response
     * @param array $headers
     * @return Response
     */
    private function setResponseHeaders(Response $response, array $headers): Response
    {
        if (!empty($headers)) {
            foreach ($headers as $name => $value) {
                $response = $response->withAddedHeader($name, $value);
            }
        }

        return $response;
    }
}
