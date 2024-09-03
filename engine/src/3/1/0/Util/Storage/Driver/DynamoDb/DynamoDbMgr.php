<?php
namespace Ntuple\Synctree\Util\Storage\Driver\DynamoDb;

use Aws\DynamoDb\BinaryValue;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\NumberValue;
use Aws\DynamoDb\SetValue;
use Exception;
use libraries\constant\CommonConst;
use Ntuple\Synctree\Crypt\AES;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Util\CommonUtil;
use stdClass;
use Throwable;

class DynamoDbMgr
{
    private const DYNAMODB_STATUS_CODE_SUCCESS = 200;

    private $config;
    private $logger;
    private $connection;
    private $marshaler;

    /**
     * DynamoDbMgr constructor.
     * @param LogMessage $logger
     * @param array|null $config
     */
    public function __construct(LogMessage $logger, array $config)
    {
        // load config
        $this->config = $this->getConfig($config);
        $this->logger = $logger;
        $this->marshaler = new Marshaler();
    }

    /**
     * @return LogMessage
     */
    public function getLogger(): LogMessage
    {
        return $this->logger;
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return array|BinaryValue|NumberValue|SetValue|false|int|stdClass|null
     * @throws DynamoDbStorageException|Exception
     */
    public function getItem(array $reqData, string $searchKey = '')
    {
        $debugInfo = debug_backtrace();

        try {
            // make connection
            $this->makeConnection();

            // getItem
            $resData = $this->connection->getItem($reqData)->toArray();

            // logging
            $this->logger->debug(
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
                throw new DynamoDbStorageException('Failed to get dynamodb data');
            }

            // check not found
            if (!isset($resData['Item']) || empty($resData['Item'])) {
                return false;
            }

            return $this->marshaler->unmarshalItem($resData['Item']);
        } catch (DynamoDbStorageException $ex) {
            throw $ex;
        } catch (DynamoDbException $ex) {
            switch ($ex->getAwsErrorCode()) {
                case 'ResourceNotFoundException':
                    throw new DynamoDbStorageException('Requested resource not found[key:'.$searchKey.']');
                case 'ValidationException':
                    throw new DynamoDbStorageException('Invalid parameter[key:'.$searchKey.']');
                default:
                    throw new DynamoDbStorageException('Failed to get item[key:'.$searchKey.']');
            }
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $searchKey);
            throw new DynamoDbStorageException('Failed to get dynamodb data');
        }
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return bool|null
     * @throws DynamoDbStorageException|Exception
     */
    public function putItem(array $reqData, string $searchKey = ''): ?bool
    {
        $debugInfo = debug_backtrace();

        try {
            // make connection
            $this->makeConnection();

            // putItem
            $resData = $this->connection->putItem($reqData)->toArray();

            // logging
            $this->logger->debug(
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
                throw new DynamoDbStorageException('Failed to put dynamodb data');
            }

            return true;
        } catch (DynamoDbStorageException $ex) {
            throw $ex;
        } catch (DynamoDbException $ex) {
            switch ($ex->getAwsErrorCode()) {
                case 'ConditionalCheckFailedException':
                    throw new DynamoDbStorageException('Requested resource already exist[key:'.$searchKey.']');
                case 'ResourceNotFoundException':
                    throw new DynamoDbStorageException('Requested resource not found[key:'.$searchKey.']');
                case 'ValidationException':
                    if ($ex->getAwsErrorMessage() === 'Item size has exceeded the maximum allowed size') {
                        throw new DynamoDbStorageException('Invalid parameter:Exceeded the maximum allowed size[key:'.$searchKey.']');
                    }
                    throw new DynamoDbStorageException('Invalid parameter[key:'.$searchKey.']');
                default:
                    throw new DynamoDbStorageException('Failed to put item[key:'.$searchKey.']');
            }
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $searchKey);
            throw new DynamoDbStorageException('Failed to put dynamodb data');
        }
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return bool|null
     * @throws DynamoDbStorageException|Exception
     */
    public function updateItem(array $reqData, string $searchKey = ''): ?bool
    {
        $debugInfo = debug_backtrace();

        try {
            // make connection
            $this->makeConnection();

            // updateItem
            $resData = $this->connection->updateItem($reqData)->toArray();

            // logging
            $this->logger->debug(
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
                throw new DynamoDbStorageException('Failed to update dynamodb data');
            }

            return true;
        } catch (DynamoDbStorageException $ex) {
            throw $ex;
        } catch (DynamoDbException $ex) {
            switch ($ex->getAwsErrorCode()) {
                case 'ConditionalCheckFailedException':
                case 'ResourceNotFoundException':
                    throw new DynamoDbStorageException('Requested resource not found[key:'.$searchKey.']');
                case 'ValidationException':
                    if ($ex->getAwsErrorMessage() === 'Item size has exceeded the maximum allowed size') {
                        throw new DynamoDbStorageException('Invalid parameter:Exceeded the maximum allowed size[key:'.$searchKey.']');
                    }
                    throw new DynamoDbStorageException('Invalid parameter[key:'.$searchKey.']');
                default:
                    throw new DynamoDbStorageException('Failed to update item[key:'.$searchKey.']');
            }
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $searchKey);
            throw new DynamoDbStorageException('Failed to update dynamodb data');
        }
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return array|false
     * @throws DynamoDbStorageException|Exception
     */
    public function queryItem(array $reqData, string $searchKey = '')
    {
        $debugInfo = debug_backtrace();

        try {
            // make connection
            $this->makeConnection();

            // query
            $resData = $this->connection->query($reqData)->toArray();

            // logging
            $this->logger->debug(
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
                throw new DynamoDbStorageException('Failed to query dynamodb data');
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
        } catch (DynamoDbStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $reqData);
            throw new DynamoDbStorageException('Failed to query dynamodb data');
        }
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @param int $limit
     * @return array|false
     * @throws DynamoDbStorageException|Exception
     */
    public function queryAllItem(array $reqData, string $searchKey = '', int $limit = -1)
    {
        $resData = [];
        $queryData = [];

        try {
            do {
                if (isset($queryData['LastEvaluatedKey'])) {
                    $reqData['ExclusiveStartKey'] = $queryData['LastEvaluatedKey'];
                }

                // query Item
                $queryData = $this->queryItem($reqData, $searchKey);
                if (empty($queryData)) {
                    return false;
                }

                // add items
                foreach ($queryData['Items'] as $item) {
                    $resData[] = $item;
                }

                // check limit count
                if ($limit > 0 && $limit <= count($resData)) {
                    break;
                }
            } while (isset($queryData['LastEvaluatedKey']));

            return $resData;
        } catch (DynamoDbStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $reqData);
            throw new DynamoDbStorageException('Failed to query all dynamodb data');
        }
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return array|false
     * @throws DynamoDbStorageException|Exception
     */
    public function scanItem(array $reqData, string $searchKey = '')
    {
        $debugInfo = debug_backtrace();

        try {
            // make connection
            $this->makeConnection();

            // scan
            $resData = $this->connection->scan($reqData)->toArray();

            // logging
            $this->logger->debug(
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
                throw new DynamoDbStorageException('Failed to scan dynamodb data');
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
        } catch (DynamoDbStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $reqData);
            throw new DynamoDbStorageException('Failed to scan dynamodb data');
        }
    }

    /**
     * @param array $reqData
     * @param string $searchKey
     * @return bool
     * @throws DynamoDbStorageException|Exception
     */
    public function deleteItem(array $reqData, string $searchKey = ''): bool
    {
        $debugInfo = debug_backtrace();

        try {
            // make connection
            $this->makeConnection();

            // deleteItem
            $resData = $this->connection->deleteItem($reqData)->toArray();

            // logging
            $this->logger->debug(
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
        } catch (DynamoDbStorageException $ex) {
            throw $ex;
        } catch (DynamoDbException $ex) {
            switch ($ex->getAwsErrorCode()) {
                case 'ConditionalCheckFailedException':
                case 'ResourceNotFoundException':
                    throw new DynamoDbStorageException('Requested resource not found[key:'.$searchKey.']');
                case 'ValidationException':
                    throw new DynamoDbStorageException('Invalid parameter[key:'.$searchKey.']');
                default:
                    throw new DynamoDbStorageException('Failed to delete item[key:'.$searchKey.']');
            }
        } catch (Throwable $ex) {
            $this->logger->exception($ex, $searchKey);
            throw new DynamoDbStorageException('Failed to delete dynamodb data');
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
     * @param $item
     * @return array|null
     */
    public function unMarshalItem($item): ?array
    {
        return $this->marshaler->unmarshalItem($item);
    }

    /**
     * @param string $tableName
     * @return string
     */
    public function makeTableName(string $tableName): string
    {
        return empty(APP_ENV_SERVICE) ?$tableName :APP_ENV_SERVICE.'_'.$tableName;
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
            $value = AES::create($this->logger)->decryptWithHmac(AES::AES_256_CBC, $value, CommonUtil::getSecureConfig(CommonConst::SECURE_DYNAMO_KEY), true);
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
            $value = AES::create($this->logger)->encryptWithHmac(AES::AES_256_CBC, $value, CommonUtil::getSecureConfig(CommonConst::SECURE_DYNAMO_KEY), true);
        }

        return $value;
    }

    public function close(): void
    {
        $this->connection = null;
    }

    /**
     * @throws DynamoDbStorageException|Exception
     */
    private function makeConnection(): void
    {
        try {
            // make connection
            $this->tryConnect($this->config);
        } catch (DynamoDbException $ex) {
            $this->logger->exception($ex);
            throw new DynamoDbStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new DynamoDbStorageException('Failed to connect');
        }
    }

    /**
     * @param array $config
     */
    private function tryConnect(array $config): void
    {
        // make connection
        if (empty($this->connection)) {
            $this->connection = new DynamoDbClient([
                'region' => $config['region'],
                'version' => $config['version'],
                'credentials' => [
                    'key' => $config['key'],
                    'secret' => $config['secret']
                ]
            ]);
        }
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
     * @param array $connectionInfo
     * @return array
     */
    private function getConfig(array $connectionInfo): array
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $config = $config['settings']['dynamo'];

        return array_merge($config, $connectionInfo);
    }
}