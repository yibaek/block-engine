<?php
namespace libraries\auth\oauth;

use libraries\auth\AuthorizationClientManager;
use libraries\auth\AuthorizationDbManager;
use libraries\auth\IAuthorization;
use libraries\auth\oauth\controller\AuthorizeController;
use libraries\auth\oauth\controller\ResourceController;
use libraries\auth\oauth\controller\TokenController;
use libraries\auth\oauth\grantType\AuthorizationCodeGrantType;
use libraries\auth\oauth\grantType\ClientCredentialsGrantType;
use libraries\auth\oauth\grantType\RefreshTokenGrantType;
use libraries\auth\oauth\grantType\Saml2BearerGrantType;
use libraries\auth\oauth\grantType\UserCredentialsGranType;
use libraries\auth\oauth\responseType\AccessTokenResponseType;
use libraries\auth\oauth\responseType\AuthorizationCodeResponseType;
use libraries\auth\oauth\responseType\JwtAccessTokenResponseType;
use libraries\auth\oauth\storage\JwtAccessToken;
use libraries\auth\Request;
use libraries\auth\Response;
use libraries\constant\AuthorizationConst;
use libraries\exception\AuthorizationInvalidException;
use models\rdb\IRdbMgr;
use models\rdb\RDbManager;
use OAuth2\ClientAssertionType\HttpBasic;
use OAuth2\Scope;
use OAuth2\TokenType\Bearer;
use RuntimeException;
use Throwable;

class OAuth2 implements IAuthorization
{
    public const AUTHORIZATION_TYPE = 'oauth2';
    private const CREDENTIAL_CREATE_BY_PORTAL = 1;

    private $server;
    private $storage;
    private $config;
    private $clientManager;
    private $validation;
    private $validateData;

    /**
     * OAuth2 constructor.
     * @param IRdbMgr $rdb
     * @param AuthorizationClientManager $clientManager
     * @param array $config
     */
    public function __construct(IRdbMgr $rdb, AuthorizationClientManager $clientManager, array $config = [])
    {
        // set client manager
        $this->clientManager = $clientManager;

        // get storage
        $this->storage = $this->getStorage($rdb);

        // set config
        $this->config = $this->addDefaultConfig($config);

        $this->validateData = [];
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function generateKey(): array
    {
        $id = bin2hex(random_bytes(8));
        $secret = bin2hex(random_bytes(32));

        // set client manager
        $this->storage->setClientManager($this->clientManager);

        // set key
        if (false === $this->storage->setKey($id, $secret)) {
            throw new RuntimeException('failed to set client certification['.$this->clientManager->getToString().']');
        }

        // set client id,secret
        $this->clientManager->setClientID($id);
        $this->clientManager->setClientSecret($secret);

        return [
            'id' => $this->clientManager->getClientID(),
            'secret' => $this->clientManager->getClientSecret()
        ];
    }

    /**
     * @return bool
     * @throws Throwable
     */
    public function deleteKey(): bool
    {
        // set client manager
        $this->storage->setClientManager($this->clientManager);

        // delete key
        if (false === $this->storage->deleteKey()) {
            throw new RuntimeException('failed to delete client certification['.$this->clientManager->getToString().']');
        }

        return true;
    }

    /**
     * @return bool
     * @throws Throwable
     */
    private function validateCertification(): bool
    {
        // set client manager
        $this->storage->setClientManager($this->clientManager);

        // check client_id
        if (!$this->clientManager->getClientID()) {
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'No client id supplied');
        }

        // check client_secret
        if (!$this->clientManager->getClientSecret()) {
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'No client secret supplied');
        }

        // get certification data
        $certificationData = $this->storage->getKey();
        if (empty($certificationData)) {
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The client id supplied is invalid');
        }

        // validate certification
        if (AuthorizationConst::AUTHORIZATION_TYPE_CODE[self::AUTHORIZATION_TYPE] !== (int)$certificationData['certification_type']
            || $this->clientManager->getClientSecret() !== $certificationData['client_secret']
            || $this->clientManager->getEnvironment() !== $certificationData['certification_environment']) {
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The credentials does not match');
        }

        // check certification match data
        $appID = $this->clientManager->getAppID();
        if ($certificationData['credential_target'] === self::CREDENTIAL_CREATE_BY_PORTAL) {
            $portalRdb = (new RDbManager())->getRdbMgr('portal');
            $portalCredentialData = $portalRdb->getHandler()->executeGetPortalCredential($certificationData['credential_id']);
            $appID = $portalCredentialData['portal_app_id'];
        }

        $certificationMatchData = $this->storage->getCertificationMatchData($appID, $certificationData['credential_target'], $certificationData['credential_id']);
        if (empty($certificationMatchData)) {
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The credentials does not match');
        }

        $verificationResult = $this->validateCertificationMatchData($certificationMatchData);
        if ($verificationResult === true) {
            return true;
        }

        throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The credentials are invalid');
    }

    /**
     * @param array $certificationMatchData
     * @return bool
     * @throws Throwable
     */
    private function validateCertificationMatchData(array $certificationMatchData): bool
    {
        $verificationResult = false;
        switch ($certificationMatchData['credential_target']) {
            case '0' :
                $verificationResult = $this->validateCertificationForStudio($certificationMatchData);
                break;
            case '1' :
                $verificationResult = $this->validateCertificationForPortal($certificationMatchData);
                break;
        }
        return $verificationResult;
    }

    /**
     * @param array $certificationMatchData
     * @return bool
     */
    private function validateCertificationForStudio(array $certificationMatchData): bool
    {
        return $this->clientManager->getAppID() === (int)$certificationMatchData['app_id'];
    }

    /**
     * @param array $certificationMatchData
     * @return bool
     * @throws Throwable
     */
    private function validateCertificationForPortal(array $certificationMatchData): bool
    {
        $portalRdb = (new RDbManager())->getRdbMgr('portal');
        $portalAppId = $certificationMatchData['app_id'];
        $studioAppId = $this->clientManager->getAppID();

        return $portalRdb->getHandler()->executeVerificationCertificationForPortal($portalAppId, $studioAppId);
    }

    /**
     * @return $this|IAuthorization
     * @throws Throwable
     */
    public function generateToken(): IAuthorization
    {
        // validate certification
        $this->validateCertification();

        // get scope util
        $scopeUtil = $this->getScopeUtil();

        // set access token response type
        $accessTokenResponseType = new AccessTokenResponseType($this->storage, $this->config);
        if (isset($this->config['use_jwt_access_tokens']) && $this->config['use_jwt_access_tokens'] === true) {
            $accessTokenResponseType = new JwtAccessTokenResponseType($this->storage, $this->storage, $this->storage, $this->config);
        }

        // create token controller
        $controller = new TokenController($accessTokenResponseType, $this->storage, [], new HttpBasic($this->storage, $this->config), $scopeUtil);
        $controller->addGrantType(new ClientCredentialsGrantType($this->storage, $this->config));
        $controller->addGrantType(new AuthorizationCodeGrantType($this->storage));
        $controller->addGrantType(new RefreshTokenGrantType($this->storage, $this->config));
        $controller->addGrantType(new Saml2BearerGrantType($this->storage, $this->config));
        $controller->addGrantType(new UserCredentialsGranType($this->storage));

        // create authorization server
        $this->server = new OAuthServer($this->storage, $this->config);
        $this->server->setScopeUtil($scopeUtil);
        $this->server->setTokenController($controller);

        $this->server->handleTokenRequest(Request::createFromGlobals(), new Response());
        return $this;
    }

    /**
     * @return IAuthorization
     * @throws Throwable
     */
    public function revokeToken(): IAuthorization
    {
        // validate certification
        $this->validateCertification();

        // create token controller
        $controller = new TokenController(new AccessTokenResponseType($this->storage, $this->config), $this->storage);

        // create authorization server
        $this->server = new OAuthServer($this->storage, $this->config);
        $this->server->setTokenController($controller);

        $this->server->handleRevokeRequest(Request::createFromGlobals(), new Response());
        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->server->getResponse()->getStatusCode();
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->server->getResponse()->getHttpHeaders();
    }

    /**
     * @return array
     */
    public function getBodys(): array
    {
        return $this->server->getResponse()->getParameters();
    }

    /**
     * @return string
     */
    public function getContents(): string
    {
        return $this->server->getResponse()->getContents();
    }

    /**
     * @return $this|IAuthorization
     * @throws Throwable
     */
    public function validation(): IAuthorization
    {
        $tokenStorage = $this->storage;
        if (isset($this->config['use_jwt_access_tokens']) && $this->config['use_jwt_access_tokens'] === true) {
            $tokenStorage = new JwtAccessToken($this->storage, $this->storage, null, $this->config);
        }

        $this->server = new OAuthServer($this->storage, $this->config);
        $resourceController = new ResourceController(new Bearer($this->config), $tokenStorage, $this->config);
        $this->server->setResourceController($resourceController);

        $scope = isset($this->config['supported_scopes']) ?implode(' ', $this->config['supported_scopes']) :null;
        $this->validation = $this->server->verifyResourceRequest(Request::createFromGlobals(), new Response(), $scope);

        // set validate data
        $this->setValidateData($resourceController);

        return $this;
    }

    /**
     * @return array|null
     */
    public function getTokenData(): ?array
    {
        return $this->server->getAccessTokenData(Request::createFromGlobals(), new Response());
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->validation;
    }

    /**
     * @return bool
     */
    public function isRequiredValid(): bool
    {
        return in_array($this->clientManager->getEnvironment(), AuthorizationConst::AUTHORIZATION_ENVIRONMENT, true);
    }

    /**
     * @return IAuthorization
     */
    public function authorize(): IAuthorization
    {
        // get scope util
        $scopeUtil = $this->getScopeUtil();

        // create authorize controller
        $controller = new AuthorizeController($this->storage, ['code' => new AuthorizationCodeResponseType($this->storage, $this->config)], $this->config, $scopeUtil);

        // create authorization server
        $this->server = new OAuthServer($this->storage, $this->config);
        $this->server->setScopeUtil($scopeUtil);
        $this->server->addGrantType(new AuthorizationCodeGrantType($this->storage));
        $this->server->setAuthorizeController($controller);

        if ($this->isAuthorizeValidateOnly()) {
            $this->server->validateAuthorizeRequest(Request::createFromGlobals(), new Response());
            return $this;
        }

        $this->server->handleAuthorizeRequest(Request::createFromGlobals(), new Response(), true, $this->config['auth_code_userid'] ?? null);
        return $this;
    }

    /**
     * @param IRdbMgr $rdb
     * @return AuthorizationDbManager
     */
    private function getStorage(IRdbMgr $rdb): AuthorizationDbManager
    {
        return new AuthorizationDbManager($rdb);
    }

    /**
     * @return Scope
     */
    private function getScopeUtil(): Scope
    {
        if (isset($this->config['supported_scopes']) && !empty($this->config['supported_scopes'])) {
            return new Scope(['supported_scopes' => $this->config['supported_scopes']]);
        }

        return new Scope($this->storage);
    }

    /**
     * @param array $config
     * @return array
     */
    private function addDefaultConfig(array $config): array
    {
        return $config;
    }

    /**
     * @return bool
     */
    public function isAuthorizeValidateOnly(): bool
    {
        return isset($this->config['authorize_validate_only']) && $this->config['authorize_validate_only'] === true;
    }

    /**
     * @param ResourceController $controller
     * @throws Throwable
     */
    private function setValidateData(ResourceController $controller): void
    {
        try {
            if ($this->isValid()) {
                $token = $controller->getToken();

                // get certification data
                if (!isset($token['client_id'])) {
                    return;
                }
                $certificationData = $this->storage->getKey($token['client_id']);
                if (empty($certificationData)) {
                    return;
                }

                // only allow by portal
                if ($certificationData['credential_target'] !== self::CREDENTIAL_CREATE_BY_PORTAL) {
                    return;
                }

                // get certification match data
                $certificationMatchData = $this->storage->getCertificationMatchDataForTransactionLog($certificationData['credential_target'], $certificationData['credential_id']);
                if (empty($certificationMatchData)) {
                    return;
                }

                $this->validateData = [
                    'certification_match' => $certificationMatchData
                ];
            }
        } catch (RuntimeException $ex) {
            return;
        }
    }

    /**
     * @return array
     */
    public function getValidateData(): array
    {
        return $this->validateData;
    }
}