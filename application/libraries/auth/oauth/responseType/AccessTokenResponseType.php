<?php
namespace libraries\auth\oauth\responseType;

use libraries\auth\AuthorizationDbManager;
use OAuth2\ResponseType\AccessTokenInterface;
use OAuth2\Storage\AccessTokenInterface as AccessTokenStorageInterface;
use OAuth2\Storage\RefreshTokenInterface;
use RuntimeException;

/**
 * @author Brent Shaffer <bshafs at gmail dot com>
 */
class AccessTokenResponseType implements AccessTokenInterface
{
    /**
     * @var AuthorizationDbManager
     */
    protected $tokenStorage;

    /**
     * @var array
     */
    protected $config;

    /**
     * @param AccessTokenStorageInterface $tokenStorage   - REQUIRED Storage class for saving access token information
     * @param array                       $config         - OPTIONAL Configuration options for the server
     * @code
     *     $config = array(
     *         'token_type' => 'bearer',            // token type identifier
     *         'access_lifetime' => 3600,           // time before access token expires
     *         'refresh_token_lifetime' => 1209600, // time before refresh token expires
     *     );
     * @endcode
     */
    public function __construct(AccessTokenStorageInterface $tokenStorage, array $config = array())
    {
        $this->tokenStorage = $tokenStorage;

        $this->config = array_merge(array(
            'token_type'             => 'Bearer',
            'access_lifetime'        => 3600,
            'refresh_token_lifetime' => 1209600,
        ), $config);
    }

    /**
     * Get authorize response
     *
     * @param array $params
     * @param mixed $user_id
     * @return array
     */
    public function getAuthorizeResponse($params, $user_id = null)
    {
        // build the URL to redirect to
        $result = array('query' => array());

        $params += array('scope' => null, 'state' => null);

        /*
         * a refresh token MUST NOT be included in the fragment
         *
         * @see http://tools.ietf.org/html/rfc6749#section-4.2.2
         */
        $includeRefreshToken = false;
        $result["fragment"] = $this->createAccessToken($params['client_id'], $user_id, $params['scope'], $includeRefreshToken);

        if (isset($params['state'])) {
            $result["fragment"]["state"] = $params['state'];
        }

        return array($params['redirect_uri'], $result);
    }

    /**
     * Handle the creation of access token, also issue refresh token if supported / desirable.
     *
     * @param mixed  $client_id           - client identifier related to the access token.
     * @param mixed  $user_id             - user ID associated with the access token
     * @param string $scope               - OPTIONAL scopes to be stored in space-separated string.
     * @param bool   $includeRefreshToken - if true, a new refresh_token will be added to the response
     * @return array
     *
     * @see http://tools.ietf.org/html/rfc6749#section-5
     * @ingroup oauth2_section_5
     */
    public function createAccessToken($client_id, $user_id, $scope = null, $includeRefreshToken = true)
    {
        $token = array(
            "access_token" => $this->generateAccessToken(),
            "expires_in" => $this->config['access_lifetime'],
            "token_type" => $this->config['token_type'],
            "scope" => $scope
        );

        $this->tokenStorage->setAccessToken($token["access_token"], $client_id, $user_id, $this->config['access_lifetime'] ? time() + $this->config['access_lifetime'] : null, $scope);

        /*
         * Issue a refresh token also, if we support them
         *
         * Refresh Tokens are considered supported if an instance of OAuth2\Storage\RefreshTokenInterface
         * is supplied in the constructor
         */
        if ($includeRefreshToken && $this->tokenStorage) {
            $token["refresh_token"] = $this->generateRefreshToken();
            $token['refresh_token_expires_in'] = $this->config['refresh_token_lifetime'];
            $expires = 0;
            if ($this->config['refresh_token_lifetime'] > 0) {
                $expires = time() + $this->config['refresh_token_lifetime'];
            }
            $this->tokenStorage->setRefreshToken($token['refresh_token'], $client_id, $user_id, $expires, $scope);
            $this->tokenStorage->makeAccessTokenPair($token["access_token"],$token["refresh_token"]);
        }

        return $token;
    }

    /**
     * Generates an unique access token.
     *
     * Implementing classes may want to override this function to implement
     * other access token generation schemes.
     *
     * @return string - A unique access token.
     *
     * @ingroup oauth2_section_4
     */
    protected function generateAccessToken()
    {
        if (function_exists('random_bytes')) {
            $randomData = random_bytes(20);
            if ($randomData !== false && strlen($randomData) === 20) {
                return bin2hex($randomData);
            }
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            $randomData = openssl_random_pseudo_bytes(20);
            if ($randomData !== false && strlen($randomData) === 20) {
                return bin2hex($randomData);
            }
        }
        if (function_exists('mcrypt_create_iv')) {
            $randomData = mcrypt_create_iv(20, MCRYPT_DEV_URANDOM);
            if ($randomData !== false && strlen($randomData) === 20) {
                return bin2hex($randomData);
            }
        }
        if (@file_exists('/dev/urandom')) { // Get 100 bytes of random data
            $randomData = file_get_contents('/dev/urandom', false, null, 0, 20);
            if ($randomData !== false && strlen($randomData) === 20) {
                return bin2hex($randomData);
            }
        }
        // Last resort which you probably should just get rid of:
        $randomData = mt_rand() . mt_rand() . mt_rand() . mt_rand() . microtime(true) . uniqid(mt_rand(), true);

        return substr(hash('sha512', $randomData), 0, 40);
    }

    /**
     * Generates an unique refresh token
     *
     * Implementing classes may want to override this function to implement
     * other refresh token generation schemes.
     *
     * @return string - A unique refresh token.
     *
     * @ingroup oauth2_section_4
     * @see OAuth2::generateAccessToken()
     */
    protected function generateRefreshToken()
    {
        return $this->generateAccessToken(); // let's reuse the same scheme for token generation
    }

    /**
     * Handle the revoking of refresh tokens, and access tokens if supported / desirable
     * RFC7009 specifies that "If the server is unable to locate the token using
     * the given hint, it MUST extend its search across all of its supported token types"
     *
     * @param $token
     * @param null $tokenTypeHint
     * @param string|null $clientID
     * @throws RuntimeException
     * @return boolean
     */
    public function revokeToken($token, $tokenTypeHint = null, $clientID = null)
    {
        // revoke refresh token
        if ($tokenTypeHint === 'refresh_token') {
            return $this->tokenStorage->revokeRefreshToken($clientID, $token);
        }
        
        if ($tokenTypeHint === 'access_token') {
            return $this->tokenStorage->revokeAccessToken($clientID, $token);
        }
        
        $revoked = $this->tokenStorage->revokeAccessToken($clientID, $token) || $this->tokenStorage->revokeRefreshToken($clientID, $token);

        return $revoked;
    }
}
