<?php
namespace libraries\auth\simplekey;

use libraries\auth\AuthorizationClientManager;
use libraries\auth\AuthorizationDbManager;
use libraries\auth\IAuthorization;
use libraries\constant\AuthorizationConst;
use libraries\exception\AuthorizationInvalidException;
use models\rdb\IRdbMgr;
use RuntimeException;
use Throwable;

class SimpleKey implements IAuthorization
{
    public const AUTHORIZATION_TYPE = 'simplekey';

    private $storage;
    private $clientManager;
    private $validation;
    private $statusCode;
    private $bodys;
    private $validateData;

    /**
     * SimpleKey constructor.
     * @param IRdbMgr $rdb
     * @param AuthorizationClientManager $clientManager
     */
    public function __construct(IRdbMgr $rdb, AuthorizationClientManager $clientManager)
    {
        $this->setBodys();
        $this->setStatusCode(200);

        // set client manager
        $this->clientManager = $clientManager;

        // get storage
        $this->storage = $this->getStorage($rdb);

        $this->validateData = [];
    }

    /**
     * @return array|string[]
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
            throw new RuntimeException('failed to set client auth certification['.$this->clientManager->getToString().']');
        }

        // set client id,secret
        $this->clientManager->setClientID($id);
        $this->clientManager->setClientSecret($secret);

        return [
            'key' => $this->clientManager->makeKey()
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

        // set key
        if (false === $this->storage->deleteKey()) {
            throw new RuntimeException('failed to del client certification['.$this->clientManager->getToString().']');
        }

        return true;
    }

    /**
     * @return IAuthorization
     * @throws Throwable
     */
    public function validation(): IAuthorization
    {
        try {
            // set client manager
            $this->storage->setClientManager($this->clientManager);

            // get certification
            $certificationData = $this->storage->getKey();
            if (empty($certificationData)) {
                throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The client credentials are invalid');
            }

            // check certification match data
            $certificationMatchData = $this->storage->getCertificationMatchData($this->clientManager->getAppID(), $certificationData['credential_target'], $certificationData['credential_id']);
            if (empty($certificationMatchData)) {
                throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The client credentials are invalid');
            }

            // validate certification
            if (AuthorizationConst::AUTHORIZATION_TYPE_CODE[self::AUTHORIZATION_TYPE] !== (int)$certificationData['certification_type']
                || $this->clientManager->getEnvironment() !== $certificationData['certification_environment']
                // || $this->clientManager->getSlaveID() !== (int)$certificationMatchData['slave_id']
                || $this->clientManager->getAppID() !== (int)$certificationMatchData['app_id']) {
                throw (new AuthorizationInvalidException())->setError(400, 'invalid_client', 'The client credentials are invalid');
            }

            // validate
            $this->validation = $this->clientManager->getClientSecret() === $certificationData['client_secret'];
            if (!$this->validation) {
                throw (new AuthorizationInvalidException())->setError(401, 'invalid_key', 'The simplekey provided is invalid');
            }

            // set validate data
            $this->validateData = [
                'certification_match' => $certificationMatchData
            ];

            return $this;
        } catch (AuthorizationInvalidException $ex) {
            $this->setBodys($ex->getBody());
            $this->setStatusCode($ex->getStatus());
            $this->validation = false;
            return $this;
        }
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
     * @param IRdbMgr $rdb
     * @return AuthorizationDbManager
     */
    private function getStorage(IRdbMgr $rdb): AuthorizationDbManager
    {
        return new AuthorizationDbManager($rdb);
    }

    /**
     * @return $this|IAuthorization
     */
    public function generateToken(): IAuthorization
    {
        return $this;
    }

    /**
     * @param int $code
     * @return $this|IAuthorization
     */
    public function setStatusCode(int $code): IAuthorization
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return [];
    }

    /**
     * @param array $body
     * @return $this|IAuthorization
     */
    public function setBodys(array $body = []): IAuthorization
    {
        $this->bodys = $body;
        return $this;
    }

    /**
     * @return array
     */
    public function getBodys(): array
    {
        return $this->bodys;
    }

    /**
     * @return array|null
     */
    public function getTokenData(): ?array
    {
        return [
            'verified' => $this->isValid()
        ];
    }

    public function getValidateData(): array
    {
        return $this->validateData;
    }

    public function authorize(): IAuthorization
    {
        // TODO: Implement authorize() method.
    }

    public function isAuthorizeValidateOnly(): bool
    {
        // TODO: Implement isAuthorizeValidateOnly() method.
    }

    public function revokeToken(): IAuthorization
    {
        // TODO: Implement revokeToken() method.
    }
}