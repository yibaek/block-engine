<?php
namespace Ntuple\Synctree\Util\Storage\Driver\DynamoDb;

use Aws\DynamoDb\Exception\DynamoDbException;
use Exception;
use libraries\constant\CommonConst;
use Ntuple\Synctree\Constant\PlanConst;
use Ntuple\Synctree\Exceptions\ProductQuotaExceededException;
use Ntuple\Synctree\Models\Redis\RedisKeys;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Plan\Unit\ProductControler;
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\RedisUtil;
use Throwable;

class DynamoDbHandlerForCommon
{
    private $storage;
    private $connection;

    /**
     * DynamoDbHandlerForCommon constructor.
     * @param PlanStorage $storage
     * @param DynamoDbMgr $connection
     */
    public function __construct(PlanStorage $storage, DynamoDbMgr $connection)
    {
        $this->storage = $storage;
        $this->connection = $connection;
    }

    /**
     * @param string $key
     * @return mixed
     * @throws DynamoDbStorageException|Exception
     */
    public function getItem(string $key)
    {
        try {
            $resData = $this->connection->getItem([
                'TableName' => $this->connection->makeTableName(PlanConst::SYNCTREE_STORAGE_COMMON_DYNAMODB_TABLE_NAME),
                'Key' => $this->connection->getMarshalItem($this->makeKey($key))
            ], $key);

            return $resData !== false ?$this->connection->uncompressWithDecrypt($resData['item']) :null;
        } catch (DynamoDbStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->connection->getLogger()->exception($ex, $key);
            throw new DynamoDbStorageException('Failed to get item[key:'.$key.']');
        }
    }

    /**
     * @param string $key
     * @param $item
     * @return bool|null
     * @throws ProductQuotaExceededException|DynamoDbStorageException|Exception
     */
    public function putItem(string $key, $item): ?bool
    {
        try {
            $limitCount = $this->storage->getProductControler()->getLimitNosqlKeyCount();
            $isUnlimit = ProductControler::PRODUCT_CONTROL_UNLIMIT_CODE === $limitCount;

            // check limit exceeded
            if (!$isUnlimit) {
                // get current count
                $currentCount = $this->getCurrentCount($limitCount);
                if ($currentCount >= $limitCount) {
                    throw new ProductQuotaExceededException('Nosql PutItem');
                }
            }

            // put item
            $resData = $this->connection->putItem([
                'TableName' => $this->connection->makeTableName(PlanConst::SYNCTREE_STORAGE_COMMON_DYNAMODB_TABLE_NAME),
                'Item' => $this->connection->getMarshalItem($this->makeItem($this->makeCommonID($key), $item)),
                'ConditionExpression' => 'attribute_not_exists(item_key)'
            ], $key);

            // increment count
            if (!$isUnlimit) {
                $this->incrementCurrentCount();
            }

            return $resData;
        } catch (DynamoDbStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->connection->getLogger()->exception($ex, $key);
            throw new DynamoDbStorageException('Failed to put item[key:'.$key.']');
        }
    }

    /**
     * @param string $key
     * @param $item
     * @return bool|null
     * @throws DynamoDbStorageException|Exception
     */
    public function updateItem(string $key, $item): ?bool
    {
        try {
            // update item
            return $this->connection->updateItem([
                'TableName' => $this->connection->makeTableName(PlanConst::SYNCTREE_STORAGE_COMMON_DYNAMODB_TABLE_NAME),
                'Key' => $this->connection->getMarshalItem($this->makeKey($key)),
                'ExpressionAttributeNames' => [
                    '#item' => 'item',
                    '#modify_date' => 'modify_date'
                ],
                'ExpressionAttributeValues' => $this->connection->getMarshalItem([
                    ':val1' => $this->connection->compressWithEncrypt($item),
                    ':val2' => date('Y-m-d H:i:s')
                ]),
                'UpdateExpression' => 'set #item = :val1, #modify_date = :val2',
                'ConditionExpression' => 'attribute_exists(item_key)'
            ], $key);
        } catch (DynamoDbStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->connection->getLogger()->exception($ex, $key);
            throw new DynamoDbStorageException('Failed to update item[key:'.$key.']');
        }
    }

    /**
     * @param string $key
     * @return bool
     * @throws DynamoDbStorageException|Exception
     */
    public function deleteItem(string $key): bool
    {
        try {
            $limitCount = $this->storage->getProductControler()->getLimitNosqlKeyCount();
            $isUnlimit = ProductControler::PRODUCT_CONTROL_UNLIMIT_CODE === $limitCount;

            // get current count
            $currentCount = 0;
            if (!$isUnlimit) {
                $currentCount = $this->getCurrentCountWithInit($limitCount);
            }

            // delete item
            $resData = $this->connection->deleteItem([
                'TableName' => $this->connection->makeTableName(PlanConst::SYNCTREE_STORAGE_COMMON_DYNAMODB_TABLE_NAME),
                'Key' => $this->connection->getMarshalItem($this->makeKey($key)),
                'ConditionExpression' => 'attribute_exists(item_key)'
            ], $key);

            // decrement count
            if (!$isUnlimit && $currentCount > 0) {
                $this->decrementCurrentCount();
            }

            return $resData;
        } catch (DynamoDbStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->connection->getLogger()->exception($ex, $key);
            throw new DynamoDbStorageException('Failed to delete item[key:'.$key.']');
        }
    }

    /**
     * @param int $limitCount
     * @return bool|int|mixed|string
     * @throws Throwable
     * @throws \JsonException
     */
    private function getCurrentCount(int $limitCount)
    {
        try {
            // get putitem current count
            $currentCount = RedisUtil::getData($this->storage->getRedisResource(), $this->makeRedisKey(), CommonConst::ACCESS_CONTROL_REDIS_DB, false);
            if (false === $currentCount) {
                // get all items
                $items = $this->queryAllItem($limitCount);
                $currentCount = !empty($items) ?count($items) :0;

                // set putitem current count
                RedisUtil::setDataWithExpire(
                    $this->storage->getRedisResource(),
                    $this->makeRedisKey(),
                    CommonConst::ACCESS_CONTROL_REDIS_DB,
                    CommonConst::ACCESS_CONTROL_NOSQL_COMMON_SESSION_EXPIRE_TIME,
                    $currentCount, false);
            }

            return $currentCount;
        } catch (Throwable $ex) {
            RedisUtil::del($this->storage->getRedisResource(), $this->makeRedisKey(), CommonConst::ACCESS_CONTROL_REDIS_DB);
            throw $ex;
        }
    }

    /**
     * @param int $limitCount
     * @return bool|int|mixed|string
     * @throws Throwable
     * @throws \JsonException
     */
    private function getCurrentCountWithInit(int $limitCount)
    {
        // get current count
        $currentCount = $this->getCurrentCount($limitCount);

        try {
            // init current count; to failover
            if ($currentCount < 0) {
                $currentCount = 0;
                RedisUtil::setDataWithExpire(
                    $this->storage->getRedisResource(),
                    $this->makeRedisKey(),
                    CommonConst::ACCESS_CONTROL_REDIS_DB,
                    CommonConst::ACCESS_CONTROL_NOSQL_COMMON_SESSION_EXPIRE_TIME,
                    $currentCount, false);
            }

            return $currentCount;
        } catch (Throwable $ex) {
            RedisUtil::del($this->storage->getRedisResource(), $this->makeRedisKey(), CommonConst::ACCESS_CONTROL_REDIS_DB);
            throw $ex;
        }
    }

    /**
     * @param string $key
     * @return array
     * @throws Throwable
     */
    private function makeKey(string $key): array
    {
        return [
            'item_key' => $this->makeCommonID($key),
            'master_id' => $this->storage->getAccountManager()->getMasterID()
        ];
    }

    /**
     * @param string $key
     * @return string
     * @throws Throwable
     */
    private function makeCommonID(string $key): string
    {
        return CommonUtil::getHashKey($this->storage->getAccountManager()->getMasterID().'_'.$key, 'sha256');
    }

    /**
     * @return string
     * @throws Throwable
     */
    private function makeRedisKey(): string
    {
        return RedisKeys::makeNosqlCommonQuotaLimitKey((string) $this->storage->getAccountManager()->getMasterID(), 'product-nosql-common');
    }

    /**
     * @param string $key
     * @param $item
     * @return array
     * @throws Throwable
     */
    private function makeItem(string $key, $item): array
    {
        $resData = [
            'item_key' => $key,
            'master_id' => $this->storage->getAccountManager()->getMasterID(),
            'item' => $this->connection->compressWithEncrypt($item),
            'slave_id' => $this->storage->getAccountManager()->getSlaveID(),
            'app_id' => $this->storage->getTransactionManager()->getAppID(),
            'bizunit_sno' => $this->storage->getTransactionManager()->getBizunitSno(),
            'transaction_key' => $this->storage->getTransactionManager()->getTransactionKey(),
            'reg_date' => date('Y-m-d H:i:s'),
            'modify_date' => date('Y-m-d H:i:s')
        ];

        if (false !== ($expireDate=$this->getExpireDate())) {
            $resData['expire_date'] = $expireDate;
        }

        return $resData;
    }

    /**
     * @param int $limitCount
     * @return array|bool
     * @throws Throwable
     */
    private function queryAllItem(int $limitCount)
    {
        try {
            return $this->connection->queryAllItem([
                'TableName' => $this->connection->makeTableName(PlanConst::SYNCTREE_STORAGE_COMMON_DYNAMODB_TABLE_NAME),
                'IndexName' => 'master_id-index',
                'KeyConditions' => [
                    'master_id' => [
                        'AttributeValueList' => [
                            $this->connection->getMarshalValue($this->storage->getAccountManager()->getMasterID())
                        ],
                        'ComparisonOperator' => 'EQ'
                    ]
                ]
            ], '', $limitCount);
        } catch (DynamoDbException $ex) {
            if ($ex->getAwsErrorCode() === 'ResourceNotFoundException') {
                return false;
            }
            throw $ex;
        }
    }

    /**
     * @return bool|float|int
     * @throws Throwable
     */
    private function getExpireDate()
    {
        if (ProductControler::PRODUCT_CONTROL_UNLIMIT_CODE === ($expirePeriod=$this->storage->getProductControler()->getLimitNosqlExpiryPeriod())) {
            return false;
        }

        return time() + ($expirePeriod*86400);
    }

    /**
     * @return int|null
     * @throws Throwable
     */
    private function incrementCurrentCount(): ?int
    {
        try {
            return RedisUtil::increment($this->storage->getRedisResource(), $this->makeRedisKey(), CommonConst::ACCESS_CONTROL_REDIS_DB);
        } catch (Throwable $ex) {
            RedisUtil::del($this->storage->getRedisResource(), $this->makeRedisKey(), CommonConst::ACCESS_CONTROL_REDIS_DB);
            throw $ex;
        }
    }

    /**
     * @return int|null
     * @throws Throwable
     */
    private function decrementCurrentCount(): ?int
    {
        try {
            return RedisUtil::decrement($this->storage->getRedisResource(), $this->makeRedisKey(), CommonConst::ACCESS_CONTROL_REDIS_DB);
        } catch (Throwable $ex) {
            RedisUtil::del($this->storage->getRedisResource(), $this->makeRedisKey(), CommonConst::ACCESS_CONTROL_REDIS_DB);
            throw $ex;
        }
    }
}