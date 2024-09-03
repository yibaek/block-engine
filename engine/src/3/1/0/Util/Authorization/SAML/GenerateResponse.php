<?php
namespace Ntuple\Synctree\Util\Authorization\SAML;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use libraries\constant\CommonConst;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\SAMLException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Protocol\Http\HttpExecutor;
use Ntuple\Synctree\Protocol\Http\HttpHandler;
use Ntuple\Synctree\Util\CommonUtil;
use Throwable;

class GenerateResponse
{
    private $storage;
    private $config;
    private $assertion;

    /**
     * GenerateResponse constructor.
     * @param PlanStorage $storage
     */
    public function __construct(PlanStorage $storage)
    {
        $this->storage = $storage;
        $this->config = [];
        $this->assertion = [];
    }

    /**
     * @param bool|null $useBase64
     * @return $this
     */
    public function setUseBase64(bool $useBase64 = null): self
    {
        if ($useBase64 !== null) {
            $this->config['use_base64'] = $useBase64;
        }
        return $this;
    }

    /**
     * @param array|null $signature
     * @return $this
     */
    public function setSignature(array $signature = null): self
    {
        if ($signature !== null) {
            $this->config['signature'] = $signature;
        }
        return $this;
    }

    /**
     * @param bool|null $useOauthBearerAssertion
     * @return $this
     */
    public function setUseOauthBearerAssertion(bool $useOauthBearerAssertion = null): self
    {
        if ($useOauthBearerAssertion !== null) {
            $this->config['use_oauth_bearer_assertion'] = $useOauthBearerAssertion;
        }
        return $this;
    }

    /**
     * @param array $assertion
     * @return $this
     */
    public function setAssertion(array $assertion): self
    {
        $this->assertion = $assertion;
        return $this;
    }

    /**
     * @param array $header
     * @param array $body
     * @return array|string
     * @throws Throwable
     * @throws GuzzleException
     * @throws ISynctreeException
     */
    public function run(array $header = [], array $body = [])
    {
        try {
            // generate http executor
            $executor = (new HttpExecutor($this->storage->getLogger(), (new HttpHandler($this->storage->getLogger()))->enableLogging()->getHandlerStack()))
                ->setEndPoint(CommonUtil::getAuthEndpoint(CommonConst::AUTHORIZATION_SAML2_ASSERTION_GENERATE))
                ->setMethod(HttpExecutor::HTTP_METHOD_POST)
                ->setHeaders($this->makeHeader($header))
                ->setBodys($this->makeBody($body))
                ->isConvertJson(true);

            // execute
            [$resStatusCode, $resHeader, $resBody] = $executor->execute();

            if (!$this->isSuccess($resBody)) {
                throw (new SAMLException($resBody['result_data']['error_description'] ?? 'An SAML Exception Occurred'))->setData([
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
        return array_merge($body, $this->assertion, $this->makeConfig(), $this->storage->getAccountManager()->getData(), $this->storage->getTransactionManager()->getData());
    }
}