<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\AccessControl\RateLimit;

use Exception;
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
use Ntuple\Synctree\Util\AccessControl\RateLimit\Rate;
use Ntuple\Synctree\Util\AccessControl\RateLimit\RateLimit as RateLimitUtil;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class RateLimit implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'access-control';
    public const ACTION = 'ratelimit';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $count;
    private $period;
    private $key;

    /**
     * RateLimit constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $count
     * @param IBlock|null $period
     * @param IBlock|null $key
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $count = null, IBlock $period = null, IBlock $key = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->count = $count;
        $this->period = $period;
        $this->key = $key ?? $this->getDefaultBlock();
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
        $this->key = isset($data['template']['key']) ?$this->setBlock($this->storage, $data['template']['key']) :$this->getDefaultBlock();

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
                'key' => $this->key->getTemplate()
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
            $status = (new RateLimitUtil($this->storage->getRedisResource(), 'access-control-ratelimit'))
                ->limit($this->getKey($blockStorage), (new Rate())->perCustom($this->getCount($blockStorage), $this->getPeriod($blockStorage)));

            // set ratelimit status
            $this->storage->getAccessControler()->setRateLimitStatus($status);
        } catch (UtilLimitExceededException $ex) {
            $this->storage->getAccessControler()->setRateLimitStatus($ex->getStatus());
            throw (new LimitExceededException($ex->getMessage()))->setData($ex->getStatus()->getExceptionData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('AccessControl-Ratelimit'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('AccessControl-Ratelimit: Invalid count: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        if ($count <= 0) {
            throw (new InvalidArgumentException('AccessControl-Ratelimit: Invalid count: Should be greater than 0'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('AccessControl-Ratelimit: Invalid period: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION);
        }

        if ($period <= 0) {
            throw (new InvalidArgumentException('AccessControl-Ratelimit: Invalid period: Should be greater than 0'))->setExceptionKey(self::TYPE, self::ACTION);
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
            throw (new InvalidArgumentException('AccessControl-Ratelimit: Invalid key: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION);
        }

        if (empty($key)) {
            return $this->makeKey();
        }

        return RedisKeys::makeAccessControlDefaultKey([$this->storage->getAccountManager()->getMasterID(), $key]);
    }

    /**
     * @return CNull
     */
    private function getDefaultBlock(): CNull
    {
        return new CNull($this->storage, $this->extra);
    }
}