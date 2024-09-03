<?php
namespace libraries\auth;

use Exception;
use JsonException;
use libraries\auth\oauth\OAuth2;
use libraries\auth\saml\Saml2;
use libraries\auth\simplekey\SimpleKey;
use libraries\constant\AuthorizationConst;
use libraries\exception\AuthorizationInvalidException;
use libraries\log\LogMessage;
use models\rdb\IRdbMgr;
use models\rdb\RDbManager;
use RuntimeException;
use Throwable;

class AuthorizationManager
{
    private $authType;
    private $clientManager;

    /**
     * AuthorizationManager constructor.
     * @param AuthorizationClientManager|null $clientManager
     * @param array $config
     */
    public function __construct(AuthorizationClientManager $clientManager = null, array $config = [])
    {
        $this->clientManager = $clientManager;
        $this->authType = $this->getAuthorizationType($this->getAuthorizationDb(), $config);
    }

    /**
     * @return string
     * @throws JsonException
     */
    public function generateKey(): string
    {
        try {
            // generate client certification
            $resData = $this->authType->generateKey();

            return (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_SUCCESS, $resData))->makeResult();
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            return (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAILURE))->makeResult($ex->getMessage());
        }
    }

    /**
     * @return string
     * @throws JsonException
     */
    public function deleteKey(): string
    {
        try {
            // delete client certification
            $this->authType->deleteKey();

            return (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_SUCCESS))->makeResult();
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            return (new AuthorizationResponseManager(AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAILURE))->makeResult($ex->getMessage());
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function generateToken(): array
    {
        try {
            // generate token
            $resData = $this->authType->generateToken();
            return [
                $resData->getStatusCode(),
                $resData->getHeaders(),
                (new AuthorizationResponseManager($this->getGenerateTokenResult($resData->getStatusCode()), $resData->getBodys()))->makeResult()
            ];
        } catch (AuthorizationInvalidException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The client credentials are invalid');
        }
    }

    /**
     * @return array
     * @throws JsonException
     */
    public function revokeToken(): array
    {
        try {
            // revoke token
            $resData = $this->authType->revokeToken();
            return [
                $resData->getStatusCode(),
                $resData->getHeaders(),
                (new AuthorizationResponseManager($this->getGenerateTokenResult($resData->getStatusCode()), $resData->getBodys()))->makeResult()
            ];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The client credentials are invalid');
        }
    }

    /**
     * @return array
     * @throws JsonException
     */
    public function validation(): array
    {
        try {
            // validate token
            $resData = $this->authType->validation();

            // get response body
            $resBody = $resData->isValid() ?$resData->getTokenData() :$resData->getBodys();

            // remove user_id field; if oauth2
            if ($this->authType instanceof OAuth2 && $resData->isValid()) {
                unset($resBody['user_id']);
            }

            return [
                $resData->getStatusCode(),
                $resData->getHeaders(),
                (new AuthorizationResponseManager($this->getValidateTokenResult($resData->isValid()), $resBody, $resData->getValidateData()))->makeResult()
            ];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The client credentials are invalid');
        }
    }

    /**
     * @return array
     * @throws JsonException
     */
    public function authorize(): array
    {
        try {
            // authorize
            $resData = $this->authType->authorize();

            $statusCode = $resData->getStatusCode();
            $header = $resData->getHeaders();
            $body = $resData->getBodys();

            // add parameters
            if (!$this->authType->isAuthorizeValidateOnly()) {
                $body['status_code'] = $resData->getStatusCode();
            }
            if (isset($header['Location'])) {
                $body['location'] = $header['Location'];
            }

            return [
                $statusCode,
                $header,
                (new AuthorizationResponseManager($this->getAuthorizeResult($statusCode, $this->authType->isAuthorizeValidateOnly()), $body))->makeResult()
            ];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The client credentials are invalid');
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function generateAuthnRequest(): array
    {
        try {
            // generate authn request
            $resData = $this->authType->generateAuthnRequest();

            $statusCode = $resData->getStatusCode();
            return [
                $statusCode,
                $resData->getHeaders(),
                (new AuthorizationResponseManager($this->getGenerateTokenResult($statusCode), $resData->getBodys()))->makeResult()
            ];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The client credentials are invalid');
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function generateAssertion(): array
    {
        try {
            // generate assertion
            $resData = $this->authType->generateAssertion();

            $statusCode = $resData->getStatusCode();
            return [
                $statusCode,
                $resData->getHeaders(),
                (new AuthorizationResponseManager($this->getGenerateTokenResult($statusCode), $resData->getBodys()))->makeResult()
            ];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The client credentials are invalid');
        }
    }

    /**
     * @return array
     * @throws JsonException
     */
    public function validateAssertion(): array
    {
        try {
            // validate assertion
            $resData = $this->authType->validateAssertion();

            return [
                $resData->getStatusCode(),
                $resData->getHeaders(),
                (new AuthorizationResponseManager($this->getValidateTokenResult($resData->isValid()), $resData->getBodys()))->makeResult()
            ];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The client credentials are invalid');
        }
    }

    /**
     * @return bool
     */
    public function isRequiredValid(): bool
    {
        return $this->authType->isRequiredValid();
    }

    /**
     * @return IRdbMgr
     */
    private function getAuthorizationDb(): IRdbMgr
    {
        return (new RDbManager())->getRdbMgr('auth');
    }

    /**
     * @param IRdbMgr $rdb
     * @param array $config
     * @return OAuth2|SimpleKey|Saml2
     */
    private function getAuthorizationType(IRdbMgr $rdb, array $config = [])
    {
        switch ($this->clientManager->getType()) {
            case SimpleKey::AUTHORIZATION_TYPE:
                return new SimpleKey($rdb, $this->clientManager);

            case OAuth2::AUTHORIZATION_TYPE:
                return new OAuth2($rdb, $this->clientManager, $config);

            case Saml2::AUTHORIZATION_TYPE:
                return new Saml2($config);

            default:
                throw new RuntimeException('invalid authorization type[type:'.$this->clientManager->getType().']');
        }
    }

    /**
     * @param int $status
     * @return string
     */
    private function getGenerateTokenResult(int $status): string
    {
        return $status === 200 ?AuthorizationConst::AUTHORIZATION_RESULT_CODE_SUCCESS :AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_GENERATE_TOKEN;
    }

    /**
     * @param bool $isValid
     * @return string
     */
    private function getValidateTokenResult(bool $isValid): string
    {
        return $isValid ?AuthorizationConst::AUTHORIZATION_RESULT_CODE_SUCCESS :AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_VALIDATE_TOKEN;
    }

    /**
     * @param int $status
     * @param bool $isValidateOnly
     * @return string
     */
    private function getAuthorizeResult(int $status, bool $isValidateOnly): string
    {
        if ($isValidateOnly) {
            return $status === 200 ?AuthorizationConst::AUTHORIZATION_RESULT_CODE_SUCCESS :AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_AUTHORIZE;
        }
        return $status === 302 ?AuthorizationConst::AUTHORIZATION_RESULT_CODE_SUCCESS :AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_AUTHORIZE;
    }
}