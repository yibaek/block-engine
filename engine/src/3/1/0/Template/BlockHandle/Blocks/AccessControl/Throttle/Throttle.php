<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\AccessControl\Throttle;

use Exception;
use libraries\constant\CommonConst;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\LimitExceededException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Models\Redis\RedisKeys;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive\CNull;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\AccessControl\Exception\LimitExceededException as UtilLimitExceededException;
use Ntuple\Synctree\Util\AccessControl\Exception\LimitExceededForBlockingException;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\BlockingConsumer;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Rate;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\RedisStorage;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\TokenBucket;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class Throttle implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'access-control';
    public const ACTION = 'throttle';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $count;
    private $period;
    private $key;
    private $expiretime;
    private $blockOption;
    private $capacity;

    /**
     * Throttle constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $count
     * @param IBlock|null $period
     * @param IBlock|null $key
     * @param IBlock|null $expiretime
     * @param IBlock|null $blockOption
     * @param IBlock|null $capacity
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $count = null, IBlock $period = null, IBlock $key = null, IBlock $expiretime = null, IBlock $blockOption = null, IBlock $capacity = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->count = $count;
        $this->period = $period;
        $this->key = $key;
        $this->expiretime = $expiretime;
        $this->blockOption = $blockOption ?? $this->getDefaultBlock();
        $this->capacity = $capacity ?? $this->getDefaultBlock();
    }

    /**
     * @param array $data
     * @return IBlock
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->count = $this->setBlock($this->storage, $data['template']['count']);
        $this->period = $this->setBlock($this->storage, $data['template']['period']);
        $this->key = $this->setBlock($this->storage, $data['template']['key']);
        $this->expiretime = $this->setBlock($this->storage, $data['template']['expiretime']);
        $this->blockOption = isset($data['template']['block-option']) ?$this->setBlock($this->storage, $data['template']['block-option']) :$this->getDefaultBlock();
        $this->capacity = isset($data['template']['capacity']) ?$this->setBlock($this->storage, $data['template']['capacity']) :$this->getDefaultBlock();

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'extra' => $this->extra->getData(),
            'template' => [
                'count' => $this->count->getTemplate(),
                'period' => $this->period->getTemplate(),
                'key' => $this->key->getTemplate(),
                'expiretime' => $this->expiretime->getTemplate(),
                'block-option' => $this->blockOption->getTemplate(),
                'capacity' => $this->capacity->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return void
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): void
    {
        try {
            $count = $this->getCount($blockStorage);
            $period = $this->getPeriod($blockStorage);
            $capacity = $this->getCapacity($blockStorage);

            // create bucket
            $bucket = new TokenBucket($this->getRedisStorage($blockStorage, $period), new Rate($count, $period), $capacity);
            $bucket->bootstrap($count);

            if (true === $this->getBlockOption($blockStorage)) {
                // blocking
                $status = (new BlockingConsumer($this->storage, $bucket))->consume();
            } else {
                // non blocking
                $status = $bucket->consume();
            }

            // set throttle limit status
            $this->storage->getAccessControler()->setRateLimitStatus($status);
        } catch (UtilLimitExceededException $ex) {
            $this->storage->getAccessControler()->setRateLimitStatus($ex->getStatus());
            throw (new LimitExceededException($ex->getMessage()))->setData($ex->getStatus()->getExceptionData());
        } catch (LimitExceededForBlockingException $ex) {
            throw new LimitExceededException($ex->getMessage());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('AccessControl-Throttle'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return string
     */
    private function makeKey(): string
    {
        $transactionManager = $this->storage->getTransactionManager();
        return RedisKeys::makeAccessControlDefaultKey([
            $transactionManager->getBizunitID(),
            $transactionManager->getBizunitVersion(),
            $transactionManager->getRevisionID(),
            $transactionManager->getEnvironment()
        ]);
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function getCount(array &$blockStorage): int
    {
        $count = $this->count->do($blockStorage);
        if (!is_int($count)) {
            throw (new InvalidArgumentException('AccessControl-Throttle: Invalid count: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        if ($count <= 0) {
            throw (new InvalidArgumentException('AccessControl-Throttle: Invalid count: Should be greater than 0'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $count;
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function getPeriod(array &$blockStorage): int
    {
        $period = $this->period->do($blockStorage);
        if (!is_int($period)) {
            throw (new InvalidArgumentException('AccessControl-Throttle: Invalid period: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION);
        }

        if ($period <= 0) {
            throw (new InvalidArgumentException('AccessControl-Throttle: Invalid period: Should be greater than 0'))->setExceptionKey(self::TYPE, self::ACTION);
        }

        return $period;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException|Throwable
     */
    private function getKey(array &$blockStorage): string
    {
        $key = $this->key->do($blockStorage);
        if (is_null($key)) {
            return $this->makeKey();
        }

        if (!is_string($key)) {
            throw (new InvalidArgumentException('AccessControl-Throttle: Invalid key: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION);
        }

        if (empty($key)) {
            return $this->makeKey();
        }

        return RedisKeys::makeAccessControlDefaultKey([$this->storage->getAccountManager()->getMasterID(), $key]);
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function getExpireTime(array &$blockStorage): int
    {
        $expiretime = $this->expiretime->do($blockStorage);
        if (is_null($expiretime)) {
            return CommonConst::REDIS_SESSION_EXPIRE_TIME_DAY_1;
        }

        if (!is_int($expiretime)) {
            throw (new InvalidArgumentException('AccessControl-Throttle: Invalid expiretime: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION);
        }

        if ($expiretime <= 0) {
            return CommonConst::REDIS_SESSION_EXPIRE_TIME_DAY_1;
        }

        return $expiretime;
    }

    /**
     * @param array $blockStorage
     * @return bool
     * @throws ISynctreeException
     */
    private function getBlockOption(array &$blockStorage): bool
    {
        $block = $this->blockOption->do($blockStorage);
        if (is_null($block)) {
            return false;
        }

        if (!is_bool($block)) {
            throw (new InvalidArgumentException('AccessControl-Throttle: Invalid block option: Not a boolean type'))->setExceptionKey(self::TYPE, self::ACTION);
        }

        return $block;
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getCapacity(array &$blockStorage): ?int
    {
        $capacity = $this->capacity->do($blockStorage);
        if (!is_null($capacity)) {
            if (!is_int($capacity)) {
                throw (new InvalidArgumentException('AccessControl-Throttle: Invalid capacity: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            if ($capacity <= 0) {
                throw (new InvalidArgumentException('AccessControl-Throttle: Invalid capacity: Should be greater than 0'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $capacity;
    }

    /**
     * @return CNull
     */
    private function getDefaultBlock(): CNull
    {
        return new CNull($this->storage, $this->extra);
    }

    /**
     * @param array $blockStorage
     * @param int $period
     * @return RedisStorage
     * @throws ISynctreeException
     * @throws Throwable
     */
    private function getRedisStorage(array &$blockStorage, int $period): RedisStorage
    {
        $key = $this->getKey($blockStorage);

        return new RedisStorage(
            $this->storage->getRedisResource()->getConnection(CommonConst::ACCESS_CONTROL_REDIS_DB, $key),
            $this->storage->getLogger(),
            $key,
            $period,
            $this->getExpireTime($blockStorage));
    }
}