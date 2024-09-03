<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Redis;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\StorageException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\Storage\Driver\Redis\AbstractRedisMgr;
use Ntuple\Synctree\Util\Storage\Driver\Redis\IRedisMgr;
use Ntuple\Synctree\Util\Storage\Driver\Redis\RedisClusterMgr;
use Ntuple\Synctree\Util\Storage\Driver\Redis\RedisMgr;
use Ntuple\Synctree\Util\Storage\Driver\Redis\RedisMgrWithSharding;
use Ntuple\Synctree\Util\Storage\Driver\Redis\RedisStorageException;
use Throwable;

class RedisCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-redis-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connector;

    /**
     * RedisCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $connector = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->connector = $connector;
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
        $this->connector = $this->setBlock($this->storage, $data['template']['connector']);

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
                'connector' => $this->connector->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return IRedisMgr
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): IRedisMgr
    {
        try {
            return $this->getConnector($blockStorage);
        } catch (RedisStorageException $ex) {
            throw (new StorageException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Redis-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return IRedisMgr
     * @throws ISynctreeException|Throwable
     */
    private function getConnector(array &$blockStorage): IRedisMgr
    {
        $connector = $this->connector->do($blockStorage);
        if (null === $connector || empty($connector) || !is_array($connector)) {
            throw (new InvalidArgumentException('Storage-Redis-Create: Invalid connection'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        // check storage type
        if ('redis' !== $connector['driver']) {
            throw (new InvalidArgumentException('Storage-Redis-Create: Invalid connection: Not a redis connection'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        switch ($connector['operation_mode']) {
            case AbstractRedisMgr::REDIS_OPERATION_MODE_MULTI:
                return new RedisMgrWithSharding($this->storage->getLogger(), $connector);

            case AbstractRedisMgr::REDIS_OPERATION_MODE_CLUSTER:
                return new RedisClusterMgr($this->storage->getLogger(), $connector);

            default:
                return new RedisMgr($this->storage->getLogger(), $connector);
        }
    }
}