<?php
namespace Ntuple\Synctree\Util\Authorization\OAuth2;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use libraries\constant\CommonConst;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\OAuthException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Protocol\Http\HttpExecutor;
use Ntuple\Synctree\Protocol\Http\HttpHandler;
use Ntuple\Synctree\Util\CommonUtil;
use Throwable;

class Authorize
{
    private $storage;
    private $config;

    /**
     * Authorize constructor.
     * @param PlanStorage $storage
     */
    public function __construct(PlanStorage $storage)
    {
        $this->storage = $storage;
        $this->config = [];
    }

    /**
     * @param array|null $supportedScopes
     * @return $this
     */
    public function setSupportedScopes(array $supportedScopes = null): self
    {
        if (null !== $supportedScopes) {
            $this->config['supported_scopes'] = $supportedScopes;
        }
        return $this;
    }

    /**
     * @param int|null $expiresIn
     * @return $this
     */
    public function setExpiresIn(int $expiresIn = null): self
    {
        if (null !== $expiresIn) {
            $this->config['auth_code_lifetime'] = $expiresIn;
        }
        return $this;
    }

    /**
     * @param string|null $userID
     * @return $this
     */
    public function setUserID(string $userID = null): self
    {
        if (null !== $userID) {
            $this->config['auth_code_userid'] = $userID;
        }
        return $this;
    }

    /**
     * @param bool|null $isValidateOnly
     * @return $this
     */
    public function setValidateOnly(bool $isValidateOnly = null): self
    {
        if (null !== $isValidateOnly) {
            $this->config['authorize_validate_only'] = $isValidateOnly;
        }
        return $this;
    }

    /**
     * @param array $header
     * @param array $body
     * @return array
     * @throws Throwable
     * @throws GuzzleException
     * @throws ISynctreeException
     */
    public function run(array $header = [], array $body = []): array
    {
        try {
            // generate http executor
            $executor = (new HttpExecutor($this->storage->getLogger(), (new HttpHandler($this->storage->getLogger()))->enableLogging()->getHandlerStack()))
                ->setEndPoint(CommonUtil::getAuthEndpoint(CommonConst::AUTHORIZATION_OAUTH2_AUTHORIZE))
                ->setMethod(HttpExecutor::HTTP_METHOD_POST)
                ->setHeaders($this->makeHeader($header))
                ->setBodys($this->makeBody($body))
                ->isConvertJson(true);

            // execute
            [$resStatusCode, $resHeader, $resBody] = $executor->execute();

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
    private function makeHeader(array $header = []): array
    {
        return ['Content-Type' => 'application/json'];
    }

    /**
     * @param array $body
     * @return array
     * @throws Throwable
     */
    private function makeBody(array $body = []): array
    {
        return array_merge($body, $this->makeConfig(), $this->storage->getAccountManager()->getData(), $this->storage->getTransactionManager()->getData());
    }
}