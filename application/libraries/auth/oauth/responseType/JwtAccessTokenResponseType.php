<?php
namespace libraries\auth\oauth\responseType;

use libraries\util\CommonUtil;
use OAuth2\Encryption\EncryptionInterface;
use OAuth2\Encryption\Jwt;
use OAuth2\ResponseType\AccessToken;
use OAuth2\Storage\AccessTokenInterface as AccessTokenStorageInterface;
use OAuth2\Storage\RefreshTokenInterface;
use OAuth2\Storage\PublicKeyInterface;
use OAuth2\Storage\Memory;

class JwtAccessTokenResponseType extends AccessToken
{
    protected $publicKeyStorage;
    protected $encryptionUtil;

    /**
     * JwtAccessToken constructor.
     * @param PublicKeyInterface|null $publicKeyStorage
     * @param AccessTokenStorageInterface|null $tokenStorage
     * @param RefreshTokenInterface|null $refreshStorage
     * @param array $config
     * @param EncryptionInterface|null $encryptionUtil
     */
    public function __construct(PublicKeyInterface $publicKeyStorage = null, AccessTokenStorageInterface $tokenStorage = null, RefreshTokenInterface $refreshStorage = null, array $config = [], EncryptionInterface $encryptionUtil = null)
    {
        $config = array_merge([
            'store_encrypted_token_string' => true,
            'token_type' => 'Bearer'
        ], $config);

        if (is_null($tokenStorage)) {
            $tokenStorage = new Memory();
        }

        if (is_null($encryptionUtil)) {
            $encryptionUtil = new Jwt();
        }

        $this->publicKeyStorage = $publicKeyStorage;
        $this->encryptionUtil = $encryptionUtil;
        parent::__construct($tokenStorage, $refreshStorage, $config);
    }

    /**
     * @param mixed $client_id
     * @param mixed $user_id
     * @param null $scope
     * @param bool $includeRefreshToken
     * @return array
     */
    public function createAccessToken($client_id, $user_id, $scope = null, $includeRefreshToken = true): array
    {
        // payload to encrypt
        $payload = $this->createPayload($client_id, $user_id, $scope);

        // Encode the payload data into a single JWT access_token string
        $access_token = $this->encodeToken($payload, $client_id);

        /*
         * Save the token to a secondary storage.  This is implemented on the
         * OAuth2\Storage\JwtAccessToken side, and will not actually store anything,
         * if no secondary storage has been supplied
         */
        $token_to_store = $this->config['store_encrypted_token_string'] ? $access_token : $payload['id'];
        $this->tokenStorage->setAccessToken($token_to_store, $client_id, $user_id, $this->config['access_lifetime'] ? time() + $this->config['access_lifetime'] : null, $scope);

        // token to return to the client
        $token = [
            'access_token' => $access_token,
            'expires_in' => $this->config['access_lifetime'],
            'token_type' => $this->config['token_type'],
            'scope' => $scope
        ];

        /*
         * Issue a refresh token also, if we support them
         *
         * Refresh Tokens are considered supported if an instance of OAuth2\Storage\RefreshTokenInterface
         * is supplied in the constructor
         */
        if ($includeRefreshToken && $this->refreshStorage) {
            $refresh_token = $this->generateRefreshToken();
            $expires = 0;
            if ($this->config['refresh_token_lifetime'] > 0) {
                $expires = time() + $this->config['refresh_token_lifetime'];
            }
            $this->refreshStorage->setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope);
            $token['refresh_token'] = $refresh_token;
            $token['refresh_token_expires_in'] = $this->config['refresh_token_lifetime'];
        }

        return $token;
    }

    /**
     * @param array $token
     * @param mixed $client_id
     * @return mixed
     */
    protected function encodeToken(array $token, $client_id = null)
    {
        if (isset($this->config['jwt']['header'])) {
            $private_key = $this->config['jwt']['header']['key'];
            $algorithm = $this->config['jwt']['header']['algo'];
        } else {
            $private_key = $this->publicKeyStorage->getPrivateKey($client_id);
            $algorithm = $this->publicKeyStorage->getEncryptionAlgorithm($client_id);
        }

        return $this->encryptionUtil->encode($token, $private_key, $algorithm);
    }

    /**
     * @param $client_id
     * @param $user_id
     * @param null $scope
     * @return array
     * @throws \Exception
     */
    protected function createPayload($client_id, $user_id, $scope = null): array
    {
        // token to encrypt
        $expires = time() + $this->config['access_lifetime'];

        $seed = CommonUtil::seed($client_id);
        $jti = bin2hex(random_bytes(8)).sprintf('%03d',strlen($client_id)).CommonUtil::shuffle($client_id, $seed).$seed;

        // init payload
        $payload = [
            'exp' => $expires,
            'iat' => time(),
            'jti' => isset($this->config['jwt']['header']) ?$this->config['jwt']['payload']['jti'] :$jti,
            'token_type' => $this->config['token_type'],
            'scope' => $scope
        ];

        // add issuer
        if (isset($this->config['jwt']['payload']['iss']) && $this->config['jwt']['payload']['iss'] !== null) {
            $payload['iss'] = $this->config['jwt']['payload']['iss'];
        }

        // add audiences
        if (isset($this->config['jwt']['payload']['aud']) && $this->config['jwt']['payload']['aud'] !== null) {
            $payload['aud'] = $this->config['jwt']['payload']['aud'];
        }

        // add subject
        if (isset($this->config['jwt']['payload']['sub']) && $this->config['jwt']['payload']['sub'] !== null) {
            $payload['sub'] = $this->config['jwt']['payload']['sub'];
        }

        // add not before
        if (isset($this->config['jwt']['payload']['nbf']) && $this->config['jwt']['payload']['nbf'] !== null) {
            $payload['nbf'] = $this->config['jwt']['payload']['nbf'];
        }

        // add add claims
        if (isset($this->config['jwt']['payload']['add-claim'])) {
            foreach ($this->config['jwt']['payload']['add-claim'] as $key => $claim) {
                $payload[$key] = $claim;
            }
        }

        if (isset($this->config['jwt_extra_payload_callable'])) {
            if (!is_callable($this->config['jwt_extra_payload_callable'])) {
                throw new \InvalidArgumentException('jwt_extra_payload_callable is not callable');
            }

            $extra = call_user_func($this->config['jwt_extra_payload_callable'], $client_id, $user_id, $scope);
            if (!is_array($extra)) {
                throw new \InvalidArgumentException('jwt_extra_payload_callable must return array');
            }

            $payload = array_merge($extra, $payload);
        }

        return $payload;
    }
}
