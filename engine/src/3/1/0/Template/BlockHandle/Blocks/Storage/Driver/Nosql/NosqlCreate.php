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
use Ntuple\Synctree\Util\Storage\Driver\DynamoDb\DynamoDbMgr;
use Ntuple\Synctree\Util\Storage\Driver\DynamoDb\DynamoDbStorageException;
use Throwable;

class NosqlCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-nosql-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connector;

    /**
     * NosqlCreate constructor.
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
     * @return DynamoDbMgr
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): DynamoDbMgr
    {
        try {
            return $this->getConnector($blockStorage);
        } catch (DynamoDbStorageException $ex) {
            throw (new StorageException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Nosql-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
        if (null === $connector || empty($connector) || !is_array($connector)) {
            throw (new InvalidArgumentException('Storage-Nosql-Create: Invalid connection'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        // check storage type
        if ('dynamodb' !== $connector['driver']) {
            throw (new InvalidArgumentException('Storage-Nosql-Create: Invalid connection: Not a nosql connection'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return new DynamoDbMgr($this->storage->getLogger(), $connector);
    }
}