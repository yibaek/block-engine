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
use Ntuple\Synctree\Util\Storage\Driver\Redis\IRedisMgr;
use Ntuple\Synctree\Util\Storage\Driver\Redis\RedisStorageException;
use Ntuple\Synctree\Util\Storage\Driver\Redis\RedisUtil;
use Throwable;

class RedisGet implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-redis-get';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connector;
    private $db;
    private $key;

    /**
     * RedisGet constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     * @param IBlock|null $key
     * @param IBlock|null $db
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $connector = null, IBlock $db = null, IBlock $key = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->connector = $connector;
        $this->db = $db;
        $this->key = $key;
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
        $this->db = $this->setBlock($this->storage, $data['template']['db']);
        $this->key = $this->setBlock($this->storage, $data['template']['key']);

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
                'connector' => $this->connector->getTemplate(),
                'db' => $this->db->getTemplate(),
                'key' => $this->key->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return bool|mixed|string
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage)
    {
        try {
            return RedisUtil::getData($this->getConnector($blockStorage), $this->getKey($blockStorage), $this->getDb($blockStorage));
        } catch (RedisStorageException $ex) {
            throw (new StorageException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Redis-Get'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return IRedisMgr
     * @throws ISynctreeException
     */
    private function getConnector(array &$blockStorage): IRedisMgr
    {
        $connector = $this->connector->do($blockStorage);
        if (!$connector instanceof IRedisMgr) {
            throw (new InvalidArgumentException('Storage-Redis-Get: Invalid connector: Not a redis connector'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $connector;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getKey(array &$blockStorage): string
    {
        $key = $this->key->do($blockStorage);
        if (!is_string($key)) {
            throw (new InvalidArgumentException('Storage-Redis-Get: Invalid key: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $key;
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function getDb(array &$blockStorage): int
    {
        $db = $this->db->do($blockStorage);
        if (!is_int($db)) {
            throw (new InvalidArgumentException('Storage-Redis-Get: Invalid db: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $db;
    }
}