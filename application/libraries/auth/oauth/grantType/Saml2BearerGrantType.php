<?php
namespace libraries\auth\oauth\grantType;

use libraries\auth\oauth\saml\Saml2Assertion;
use OAuth2\ClientAssertionType\HttpBasic;
use OAuth2\GrantType\GrantTypeInterface;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use OAuth2\ResponseType\AccessTokenInterface;
use Throwable;

class Saml2BearerGrantType extends HttpBasic implements GrantTypeInterface
{
    private $clientData;

    /**
     * @return string
     */
    public function getQuerystringIdentifier(): string
    {
        return 'urn:ietf:params:oauth:grant-type:saml2-bearer';
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return bool
     * @throws Throwable
     */
    public function validateRequest(RequestInterface $request, ResponseInterface $response): bool
    {
        if(!($validate=parent::validateRequest($request, $response))) {
            return $validate;
        }

        if (!$request->request("assertion", false)) {
            $response->setError(400, 'invalid_request', 'Missing parameters: "assertion" required');
            return false;
        }

        return (new Saml2Assertion($request, $response, $this->config))->validate();
    }

    /**
     * @param AccessTokenInterface $accessToken
     * @param mixed $client_id
     * @param mixed $user_id
     * @param string $scope
     * @return array
     */
    public function createAccessToken(AccessTokenInterface $accessToken, $client_id, $user_id, $scope): array
    {
        $includeRefreshToken = isset($this->config['use_refresh_token']) && $this->config['use_refresh_token'];

        return $accessToken->createAccessToken($client_id, $user_id, $scope, $includeRefreshToken);
    }

    /**
     * @return string|null
     */
    public function getScope(): ?string
    {
        $this->loadClientData();
        return $this->clientData['scope'] ?? null;
    }

    /**
     * @return mixed
     */
    public function getUserId()
    {
        $this->loadClientData();
        return $this->clientData['user_id'] ?? null;
    }

    private function loadClientData()
    {
        if (!$this->clientData) {
            $this->clientData = $this->storage->getClientDetails($this->getClientId());
        }
    }
}