<?php
namespace Ntuple\Synctree\Util\Authorization\OAuth2;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use libraries\constant\CommonConst;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\OAuthException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Plan\Unit\AuthDataManager;
use Ntuple\Synctree\Protocol\Http\HttpExecutor;
use Ntuple\Synctree\Protocol\Http\HttpHandler;
use Ntuple\Synctree\Util\CommonUtil;
use Throwable;

class ValidateToken
{
    private $storage;
    private $token;
    private $config;

    /**
     * ValidateToken constructor.
     * @param PlanStorage $storage
     */
    public function __construct(PlanStorage $storage)
    {
        $this->storage = $storage;
        $this->config = [];
    }

    /**
     * @param string|null $token
     * @return $this
     */
    public function setToken(string $token = null): self
    {
        if (!empty($token)) {
            $this->token = $token;
        }
        return $this;
    }

    /**
     * @param array|null $tokenType
     * @return $this
     */
    public function setTokenType(array $tokenType = null): self
    {
        if (!empty($tokenType)) {
            $this->config = array_merge($this->config, $tokenType);
        }
        return $this;
    }

    /**
     * @param array|null $supportedScopes
     * @return $this
     */
    public function setSupportedScopes(array $supportedScopes = null): self
    {
        if (!empty($supportedScopes)) {
            $this->config['supported_scopes'] = $supportedScopes;
        }
        return $this;
    }

    /**
     * @param array $header
     * @param array|string $body
     * @return array
     * @throws Throwable
     * @throws GuzzleException
     * @throws ISynctreeException
     */
    public function run(array $header = [], $body): array
    {
        try {
            // generate http executor
            $executor = (new HttpExecutor($this->storage->getLogger(), (new HttpHandler($this->storage->getLogger()))->enableLogging()->getHandlerStack()))
                ->setEndPoint(CommonUtil::getAuthEndpoint(CommonConst::AUTHORIZATION_OAUTH2_TOKEN_VALIDATE))
                ->setMethod(HttpExecutor::HTTP_METHOD_POST)
                ->setHeaders($this->makeHeader($header))
                ->setBodys($this->makeBody($body))
                ->isConvertJson(true);

            // execute
            [$resStatusCode, $resHeader, $resBody] = $executor->execute();

            // set auth extra data
            $this->storage->setAuthDataManager((new AuthDataManager())->setExtraData($this->getExtraData($resBody)));

            if (!$this->isSuccess($resBody)) {
                throw (new OAuthException($resBody['result_data']['error_description'] ?? 'An OAuth Exception Occurred'))->setData([
                    'status-code' => $resStatusCode,
                    'header' => $resHeader,
                    'body' => $resBody
                ]);
            }

            return $resBody['result_data'];
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * @param array $data
     * @return bool
     */
    private function isSuccess(array $data): bool
    {
        return isset($data['result']) && $data['result'] === '0000';
    }

    /**
     * @return array
     */
    private function makeConfig(): array
    {
        return [
            'config' => $this->config
        ];
    }

    /**
     * @param array $header
     * @return array|string[]
     */
    private function makeHeader(array $header): array
    {
        $resData = ['Content-Type' => 'application/json'];

        if ($this->token !== null) {
            $resData['Authorization'] = $this->token;
            return $resData;
        }

        if (isset($header[strtoupper('Authorization')])) {
            $resData['Authorization'] = $header[strtoupper('Authorization')];
        }

        return $resData;
    }

    /**
     * @param array|string $body
     * @return array
     */
    private function makeBody($body): array
    {
        if (is_array($body)) {
            return array_merge($body, $this->makeConfig(), $this->storage->getTransactionManager()->getData());
        }

        return array_merge($this->makeConfig(), $this->storage->getTransactionManager()->getData());
    }

    /**
     * @param array $body
     * @return array
     */
    private function getExtraData(array $body): array
    {
        return $body['extra_data'] ?? [];
    }
}