<?php
namespace Ntuple\Synctree\Util\Authorization\SimpleKey;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use libraries\constant\CommonConst;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\SimpleKeyException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Plan\Unit\AuthDataManager;
use Ntuple\Synctree\Protocol\Http\HttpExecutor;
use Ntuple\Synctree\Protocol\Http\HttpHandler;
use Ntuple\Synctree\Util\CommonUtil;
use Throwable;

class ValidateSimpleKey
{
    private $storage;
    private $key;

    /**
     * ValidateSimpleKey constructor.
     * @param PlanStorage $storage
     * @param string|null $key
     */
    public function __construct(PlanStorage $storage, string $key = null)
    {
        $this->storage = $storage;
        $this->key = $key;
    }

    /**
     * @param array $header
     * @param array $body
     * @return array
     * @throws GuzzleException
     * @throws Throwable
     * @throws ISynctreeException
     */
    public function run(array $header = [], array $body = []): array
    {
        try {
            // generate http executor
            $executor = (new HttpExecutor($this->storage->getLogger(), (new HttpHandler($this->storage->getLogger()))->enableLogging()->getHandlerStack()))
                ->setEndPoint(CommonUtil::getAuthEndpoint(CommonConst::AUTHORIZATION_SIMPLEKEY_VALIDATE))
                ->setMethod(HttpExecutor::HTTP_METHOD_POST)
                ->setHeaders($this->makeHeader($header))
                ->setBodys($this->makeBody($body))
                ->isConvertJson(true);

            // execute
            [$resStatusCode, $resHeader, $resBody] = $executor->execute();

            // set auth extra data
            $this->storage->setAuthDataManager((new AuthDataManager())->setExtraData($this->getExtraData($resBody)));

            if (!$this->isSuccess($resBody)) {
                throw (new SimpleKeyException($resBody['result_data']['error_description'] ?? 'An SimpleKey Exception Occurred'))->setData([
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
     * @param array $header
     * @return array|string[]
     */
    private function makeHeader(array $header): array
    {
        $resData = ['Content-Type' => 'application/json'];

        if ($this->key !== null) {
            $resData[CommonConst::AUTHORIZATION_SIMPLE_KEY_HEADER] = $this->key;
            return $resData;
        }

        // set key in header
        $resData[CommonConst::AUTHORIZATION_SIMPLE_KEY_HEADER] = $header[strtoupper(CommonConst::AUTHORIZATION_SIMPLE_KEY_HEADER)] ?? '';

        return $resData;
    }

    /**
     * @param array $body
     * @return array
     * @throws Throwable
     */
    private function makeBody(array $body = []): array
    {
        return array_merge($body, $this->storage->getAccountManager()->getData(), $this->storage->getTransactionManager()->getData());
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