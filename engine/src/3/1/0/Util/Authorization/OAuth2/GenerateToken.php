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

class GenerateToken
{
    private $storage;
    private $config;
    private $extensionBody;

    /**
     * GenerateToken constructor.
     * @param PlanStorage $storage
     */
    public function __construct(PlanStorage $storage)
    {
        $this->storage = $storage;
        $this->config = [];
        $this->extensionBody = [];
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
     * @param int|null $expiresIn
     * @return $this
     */
    public function setExpiresIn(int $expiresIn = null): self
    {
        if (null !== $expiresIn && $expiresIn > 0) {
            $this->config['access_lifetime'] = $expiresIn;
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
     * @param int|null $lifetime
     * @return $this
     */
    public function setRefreshTokenLifeTime(int $lifetime = null): self
    {
        if ($lifetime !== null) {
            $this->config['refresh_token_lifetime'] = $lifetime;
        }
        return $this;
    }

    /**
     * @param bool $alwaysIssueNewToken
     * @return $this
     */
    public function setAlwaysIssueNewRefreshToken(bool $alwaysIssueNewToken = null): self
    {
        if ($alwaysIssueNewToken !== null) {
            $this->config['always_issue_new_refresh_token'] = $alwaysIssueNewToken;
        }
        return $this;
    }

    /**
     * @param array|null $extension
     * @return $this
     */
    public function setExtension(array $extension = null): self
    {
        if ($extension !== null) {
            if (isset($extension['config'])) {
                $this->config = array_merge($this->config, $extension['config']);
            }
            if (isset($extension['assertion']) && !empty($extension['assertion'])) {
                $this->extensionBody['assertion'] = $extension['assertion'];
            }
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
                ->setEndPoint(CommonUtil::getAuthEndpoint(CommonConst::AUTHORIZATION_OAUTH2_TOKEN_GENERATE))
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
        $resData = ['Content-Type' => 'application/json'];

        if (isset($header[strtoupper('Authorization')])) {
            $resData['Authorization'] = $header[strtoupper('Authorization')];
        }

        return $resData;
    }

    /**
     * @param array $body
     * @return array
     * @throws Throwable
     */
    private function makeBody(array $body = []): array
    {
        return array_merge($body, $this->extensionBody, $this->makeConfig(), $this->storage->getAccountManager()->getData(), $this->storage->getTransactionManager()->getData());
    }
}