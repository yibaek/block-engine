<?php
namespace libraries\auth\oauth\responseType;

use OAuth2\ResponseType\AuthorizationCodeInterface;
use OAuth2\Storage\AuthorizationCodeInterface as AuthorizationCodeStorageInterface;

/**
 * @author Brent Shaffer <bshafs at gmail dot com>
 */
class AuthorizationCodeResponseType implements AuthorizationCodeInterface
{
    protected $storage;
    protected $config;

    /**
     * AuthorizationCodeResponseType constructor.
     * @param AuthorizationCodeStorageInterface $storage
     * @param array $config
     */
    public function __construct(AuthorizationCodeStorageInterface $storage, array $config = [])
    {
        $this->storage = $storage;
        $this->config = array_merge([
            'enforce_redirect' => false,
            'auth_code_lifetime' => 30,
        ], $config);
    }

    /**
     * @param array $params
     * @param null $user_id
     * @return array
     * @throws \Exception
     */
    public function getAuthorizeResponse($params, $user_id = null): array
    {
        // build the URL to redirect to
        $result = ['query' => []];

        $params += ['scope' => null, 'state' => null];

        $result['query']['code'] = $this->createAuthorizationCode($params['client_id'], $user_id, $params['redirect_uri'], $params['scope']);

        if (isset($params['state'])) {
            $result['query']['state'] = $params['state'];
        }

        return [$params['redirect_uri'], $result];
    }

    /**
     * Handle the creation of the authorization code.
     *
     * @param $client_id
     * Client identifier related to the authorization code
     * @param $user_id
     * User ID associated with the authorization code
     * @param $redirect_uri
     * An absolute URI to which the authorization server will redirect the
     * user-agent to when the end-user authorization step is completed.
     * @param $scope
     * (optional) Scopes to be stored in space-separated string.
     *
     * @return false|string
     * @throws \Exception
     * @see http://tools.ietf.org/html/rfc6749#section-4
     * @ingroup oauth2_section_4
     */
    public function createAuthorizationCode($client_id, $user_id, $redirect_uri, $scope = null)
    {
        $code = $this->generateAuthorizationCode();
        $this->storage->setAuthorizationCode($code, $client_id, $user_id, rawurlencode($redirect_uri), time() + $this->config['auth_code_lifetime'], $scope);

        return $code;
    }

    /**
     * @return mixed TRUE if the grant type requires a redirect_uri, FALSE if not
     * TRUE if the grant type requires a redirect_uri, FALSE if not
     */
    public function enforceRedirect()
    {
        return $this->config['enforce_redirect'];
    }

    /**
     * Generates an unique auth code.
     *
     * Implementing classes may want to override this function to implement
     * other auth code generation schemes.
     *
     * @return false|string An unique auth code.
     * An unique auth code.
     *
     * @throws \Exception
     * @ingroup oauth2_section_4
     */
    protected function generateAuthorizationCode()
    {
//        return substr(hash('sha512', random_bytes(100)), 0, 40);
        return bin2hex(random_bytes(20));
    }
}
