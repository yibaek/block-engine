<?php
namespace models\dynamo;

use Aws\DynamoDb\BinaryValue;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\NumberValue;
use Aws\DynamoDb\SetValue;
use Exception;
use libraries\constant\CommonConst;
use libraries\crypt\AES;
use libraries\log\LogMessage;
use libraries\util\CommonUtil;
use RuntimeException;
use stdClass;
use Throwable;

class DynamoDbMgr
{
    private const DYNAMODB_STATUS_CODE_SUCCESS = 200;

    private $config;
    private $client;
    private $marshaler;

    /**
     * DynamoDbMgr constructor.
     * @param array|null $config
     */
    public function __construct(array $config = null)
    {
        // load config
        $this->config = !empty($config) ?$config :$this->getConfig();

        $this->client = new DynamoDbClient([
            'region' => $this->config['region'],
            'version' => $this->config['version'],
            'credentials' => [
                'key' => $this->config['key'],
                'secret' => $this->config['secret']
            ]
        ]);

        $this->marshaler = new Marshaler();
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return array|BinaryValue|NumberValue|SetValue|bool|int|stdClass|null
     * @throws Throwable
     */
    public function getItem(array $reqData, string $searchKey = '')
    {
        $debugInfo = debug_backtrace();

        try {
            // getItem
            $resData = $this->client->getItem($reqData)->toArray();

            // logging
            LogMessage::debug(
                'key['
                . $searchKey
                . ']_req['
                . json_encode($reqData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']_res['
                . json_encode($resData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']',
                $debugInfo[2]['class'],
                $debugInfo[1]['function'],
                $debugInfo[1]['line']);

            // check status code
            if (self::DYNAMODB_STATUS_CODE_SUCCESS !== $resData['@metadata']['statusCode']) {
                throw new RuntimeException('failed to get dynamodb data');
            }

            // check not found
            if (!isset($resData['Item']) || empty($resData['Item'])) {
                return false;
            }

            return $this->marshaler->unmarshalItem($resData['Item']);
        } catch (Throwable $ex) {
            LogMessage::exception($ex, $reqData);
            throw $ex;
        }
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return bool|null
     * @throws Throwable
     */
    public function putItem(array $reqData, string $searchKey = ''): ?bool
    {
        $debugInfo = debug_backtrace();

        try {
            // putItem
            $resData = $this->client->putItem($reqData)->toArray();

            // logging
            LogMessage::debug(
                'key['
                . $searchKey
                . ']_req['
                . json_encode($reqData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']_res['
                . json_encode($resData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']',
                $debugInfo[2]['class'],
                $debugInfo[1]['function'],
                $debugInfo[1]['line']);

            // check status code
            if (self::DYNAMODB_STATUS_CODE_SUCCESS !== $resData['@metadata']['statusCode']) {
                throw new RuntimeException('failed to put dynamodb data');
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex, $reqData);
            throw $ex;
        }
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return bool|null
     * @throws Throwable
     */
    public function updateItem(array $reqData, string $searchKey = ''): ?bool
    {
        $debugInfo = debug_backtrace();

        try {
            // updateItem
            $resData = $this->client->updateItem($reqData)->toArray();

            // logging
            LogMessage::debug(
                'key['
                . $searchKey
                . ']_req['
                . json_encode($reqData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']_res['
                . json_encode($resData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']',
                $debugInfo[2]['class'],
                $debugInfo[1]['function'],
                $debugInfo[1]['line']);

            // check status code
            if (self::DYNAMODB_STATUS_CODE_SUCCESS !== $resData['@metadata']['statusCode']) {
                throw new RuntimeException('failed to update dynamodb data');
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex, $reqData);
            throw $ex;
        }
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return array|bool
     * @throws Throwable
     */
    public function queryItem(array $reqData, string $searchKey = '')
    {
        $debugInfo = debug_backtrace();

        try {
            // query
            $resData = $this->client->query($reqData)->toArray();

            // logging
            LogMessage::debug(
                'key['
                . $searchKey
                . ']_req['
                . json_encode($reqData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']_res['
                . json_encode($resData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']',
                $debugInfo[2]['class'],
                $debugInfo[1]['function'],
                $debugInfo[1]['line']);

            // check status code
            if (self::DYNAMODB_STATUS_CODE_SUCCESS !== $resData['@metadata']['statusCode']) {
                throw new RuntimeException('failed to query dynamodb data');
            }

            // check not found
            if (!isset($resData['Items']) || empty($resData['Items'])) {
                return false;
            }

            // init return data
            $returnData = [
                'Items' => [],
                'LastEvaluatedKey' => null
            ];

            // set pagination key
            if (isset($resData['LastEvaluatedKey'])) {
                $returnData['LastEvaluatedKey'] =  $resData['LastEvaluatedKey'];
            }

            // push items
            foreach ($resData['Items'] as $item) {
                $returnData['Items'][] = $this->marshaler->unmarshalItem($item);
            }

            return $returnData;
        } catch (Throwable $ex) {
            LogMessage::exception($ex, $reqData);
            throw $ex;
        }
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return array|bool
     * @throws Throwable
     */
    public function scanItem(array $reqData, string $searchKey = '')
    {
        $debugInfo = debug_backtrace();

        try {
            // scan
            $resData = $this->client->scan($reqData)->toArray();

            // logging
            LogMessage::debug(
                'key['
                . $searchKey
                . ']_req['
                . json_encode($reqData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']_res['
                . json_encode($resData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']',
                $debugInfo[2]['class'],
                $debugInfo[1]['function'],
                $debugInfo[1]['line']);

            // check status code
            if (self::DYNAMODB_STATUS_CODE_SUCCESS !== $resData['@metadata']['statusCode']) {
                throw new RuntimeException('failed to scan dynamodb data');
            }

            // check not found
            if (!isset($resData['Items']) || empty($resData['Items'])) {
                return false;
            }

            // init return data
            $returnData = [
                'Items' => [],
                'LastEvaluatedKey' => null
            ];

            // set pagination key
            if (isset($resData['LastEvaluatedKey'])) {
                $returnData['LastEvaluatedKey'] =  $resData['LastEvaluatedKey'];
            }

            // push items
            foreach ($resData['Items'] as $item) {
                $returnData['Items'][] = $this->marshaler->unmarshalItem($item);
            }

            return $returnData;
        } catch (Throwable $ex) {
            LogMessage::exception($ex, $reqData);
            throw $ex;
        }
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return bool
     * @throws Throwable
     */
    public function deleteItem(array $reqData, string $searchKey = ''): bool
    {
        $debugInfo = debug_backtrace();

        try {
            // deleteItem
            $resData = $this->client->deleteItem($reqData)->toArray();

            // logging
            LogMessage::debug(
                'key['
                . $searchKey
                . ']_req['
                . json_encode($reqData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']_res['
                . json_encode($resData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                . ']',
                $debugInfo[2]['class'],
                $debugInfo[1]['function'],
                $debugInfo[1]['line']);

            // check status code
            return !(self::DYNAMODB_STATUS_CODE_SUCCESS !== $resData['@metadata']['statusCode']);
        } catch (Throwable $ex) {
            LogMessage::exception($ex, $reqData);
            throw $ex;
        }
    }

    /**
     * @param $item
     * @return array
     */
    public function getMarshalItem(array $item): array
    {
        return $this->marshaler->marshalItem($item);
    }

    /**
     * @param $item
     * @return array|null
     */
    public function getMarshalValue($item): ?array
    {
        return $this->marshaler->marshalValue($item);
    }

    /**
     * @param string $tableName
     * @return string
     */
    public function makeTableName(string $tableName): string
    {
        if (empty(APP_ENV_SERVICE)) {
            return $tableName;
        }

        return APP_ENV_SERVICE . '_' . $tableName;
    }

    /**
     * @param $value
     * @return mixed|string|null
     * @throws Exception
     */
    public function uncompressWithDecrypt($value)
    {
        // decryption
        if ($this->getIsCrypt()) {
            $value = AES::decryptWithHmac(AES::AES_256_CBC, $value, CommonUtil::getSecureConfig(CommonConst::SECURE_DYNAMO_KEY), true);
        }

        // uncompress
        if ($this->getIsCompress()) {
            $value = unserialize(zstd_uncompress(base64_decode($value)), ['allowed_classes' => false]);
        }

        return $value;
    }

    /**
     * @param $value
     * @return mixed|string|null
     * @throws Exception
     */
    public function compressWithEncrypt($value)
    {
        // compress
        if ($this->getIsCompress()) {
            $value = base64_encode(zstd_compress(serialize($value)));
        }

        // encryption
        if ($this->getIsCrypt()) {
            $value = AES::encryptWithHmac(AES::AES_256_CBC, $value, CommonUtil::getSecureConfig(CommonConst::SECURE_DYNAMO_KEY), true);
        }

        return $value;
    }

    /**
     * @return bool
     */
    private function getIsCrypt(): bool
    {
        return $this->config['crypt'];
    }

    /**
     * @return bool
     */
    private function getIsCompress(): bool
    {
        return $this->config['compress'];
    }

    /**
     * @return array
     */
    private function getConfig(): array
    {
        $credential = CommonUtil::getCredentialConfig('dynamo');

        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $config = $config['settings']['dynamo'];

        return array_merge($credential, $config);
    }
}