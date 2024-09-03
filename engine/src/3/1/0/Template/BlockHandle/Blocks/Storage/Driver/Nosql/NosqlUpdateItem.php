<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Nosql;

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
use Ntuple\Synctree\Util\Storage\Driver\DynamoDb\DynamoDbHandlerForCommon;
use Ntuple\Synctree\Util\Storage\Driver\DynamoDb\DynamoDbMgr;
use Ntuple\Synctree\Util\Storage\Driver\DynamoDb\DynamoDbStorageException;
use Throwable;

class NosqlUpdateItem implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-nosql-updateitem';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connector;
    private $key;
    private $item;

    /**
     * NosqlUpdateItem constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     * @param IBlock|null $key
     * @param IBlock|null $item
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $connector = null, IBlock $key = null, IBlock $item = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->connector = $connector;
        $this->key = $key;
        $this->item = $item;
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
        $this->key = $this->setBlock($this->storage, $data['template']['key']);
        $this->item = $this->setBlock($this->storage, $data['template']['item']);

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
                'key' => $this->key->getTemplate(),
                'item' => $this->item->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return bool|null
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): ?bool
    {
        try {
            return (new DynamoDbHandlerForCommon($this->storage, $this->getConnector($blockStorage)))->updateItem($this->getKey($blockStorage), $this->getItem($blockStorage));
        } catch (DynamoDbStorageException $ex) {
            throw (new StorageException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Nosql-Update'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return DynamoDbMgr
     * @throws ISynctreeException
     */
    private function getConnector(array &$blockStorage): DynamoDbMgr
    {
        $connector = $this->connector->do($blockStorage);
        if (!$connector instanceof DynamoDbMgr) {
            throw (new InvalidArgumentException('Storage-Nosql-Update: Invalid connector: Invalid connector type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Storage-Nosql-Update: Invalid key: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $key;
    }

    /**
     * @param array $blockStorage
     * @return mixed
     */
    private function getItem(array &$blockStorage)
    {
        return $this->item->do($blockStorage);
    }
}