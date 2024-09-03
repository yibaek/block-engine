<?php
namespace libraries\auth;

use Exception;
use libraries\auth\simplekey\SimpleKey;
use libraries\constant\AuthorizationConst;
use libraries\exception\AuthorizationInvalidException;
use libraries\util\CommonUtil;

class AuthorizationClientManager
{
    private $type;
    private $slaveID;
    private $appID;
    private $credentialID;
    private $environment;
    private $clientID;
    private $clientSecret;

    /**
     * AuthorizationClientManager constructor.
     * @param string $type
     * @param int|null $slaveID
     * @param int|null $appID
     * @param string|null $credentialID
     * @param string|null $environment
     * @param string|null $clientID
     * @param string|null $clientSecret
     */
    public function __construct(string $type, int $slaveID = null, int $appID = null, string $credentialID = null, string $environment = null, string $clientID = null, string $clientSecret = null)
    {
        $this->type = $type;
        $this->slaveID = $slaveID;
        $this->appID = $appID;
        $this->credentialID = $credentialID;
        $this->environment = $environment;
        $this->clientID = $clientID;
        $this->clientSecret = $clientSecret;
    }

    /**
     * @param string $type
     * @param array $params
     * @return static
     * @throws Exception
     */
    public static function createFromParam(string $type, array $params = []): self
    {
        $data = self::parseParam($type, $params);
        return new static($type, $data['slave_id'], $data['appid'], $data['credential_id'], $data['environment'], $data['client_id'], $data['client_secret']);
    }

    /**
     * @return int|null
     */
    public function getSlaveID(): ?int
    {
        return $this->slaveID;
    }

    /**
     * @return int|null
     */
    public function getAppID(): ?int
    {
        return $this->appID;
    }

    /**
     * @return string|null
     */
    public function getCredentialID(): ?string
    {
        return $this->credentialID;
    }

    /**
     * @return string|null
     */
    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    /**
     * @return string|null
     */
    public function getClientID(): ?string
    {
        return $this->clientID;
    }

    /**
     * @return string|null
     */
    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getTypeCode(): int
    {
        return AuthorizationConst::AUTHORIZATION_TYPE_CODE[$this->getType()];
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setClientID(string $id): self
    {
        $this->clientID = $id;
        return $this;
    }

    /**
     * @param string $secret
     * @return $this
     */
    public function setClientSecret(string $secret): self
    {
        $this->clientSecret = $secret;
        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function generateKey(): self
    {
        $this->setClientID(bin2hex(random_bytes(8)));
        $this->setClientSecret(bin2hex(random_bytes(32)));
        return $this;
    }

    /**
     * @return string
     */
    public function getToString(): string
    {
        return implode(',', [
            'type:'.$this->type,
            'slave_id:'.$this->getSlaveID(),
            'appid:'.$this->getAppID(),
            'credential_id:'.$this->getCredentialID(),
            'environment:'.$this->getEnvironment(),
            'client_id:'.$this->getClientID(),
            'client_secret:'.$this->getClientSecret()
        ]);
    }

    /**
     * @return string
     */
    public function makeKey(): string
    {
        return $this->getClientID().':'.$this->getClientSecret();
    }

    /**
     * @param string $type
     * @param array $params
     * @return array|null[]
     * @throws Exception
     */
    private static function parseParam(string $type, array $params): array
    {
        if ($type === SimpleKey::AUTHORIZATION_TYPE && isset($params['key'])) {
            $data = explode(':', $params['key']);
            if (2 !== count($data)) {
                throw (new AuthorizationInvalidException())->setError(401, 'invalid_key', 'The simplekey provided is invalid');
            }

            $clientID = $data[0];
            $clientSecret = $data[1];
        } else {
            $clientID = $params['client_id'] ?? null;
            $clientSecret = $params['client_secret'] ?? null;
        }

        return [
            'slave_id' => $params['slave_id'] ?? null,
            'appid' => $params['appid'] ?? null,
            'credential_id' => $params['credential_id'] ?? null,
            'environment' => $params['environment'] ?? null,
            'client_id' => $clientID,
            'client_secret' => $clientSecret
        ];
    }

    /**
     * @param array $headers
     * @return mixed
     */
    public static function getSimpleKeyInHeader(array $headers)
    {
        // get simple key
        $simpleKey = CommonUtil::intersectHeader($headers, [AuthorizationConst::AUTHORIZATION_SIMPLE_KEY => null]);

        if (empty($simpleKey)) {
            throw (new AuthorizationInvalidException())->setError(400, 'invalid_key', 'The simplekey was not found in the headers or body');
        }

        return current($simpleKey);
    }
}