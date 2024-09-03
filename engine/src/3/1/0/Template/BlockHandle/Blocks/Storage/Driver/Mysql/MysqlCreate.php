<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mysql;

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
use Ntuple\Synctree\Util\Storage\Driver\Mysql\MysqlMgr;
use Ntuple\Synctree\Util\Storage\Driver\Mysql\MysqlStorageException;
use Throwable;

class MysqlCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-mysql-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connector;

    /**
     * MysqlCreate constructor.
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
     * @return MysqlMgr
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): MysqlMgr
    {
        try {
            return $this->getConnector($blockStorage);
        } catch (MysqlStorageException $ex) {
            throw (new StorageException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Mysql-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return MysqlMgr
     * @throws ISynctreeException
     */
    private function getConnector(array &$blockStorage): MysqlMgr
    {
        $connector = $this->connector->do($blockStorage);
        if (null === $connector || empty($connector) || !is_array($connector)) {
            throw (new InvalidArgumentException('Storage-Mysql-Create: Invalid connection'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        // check storage type
        if ('mysql' !== $connector['driver']) {
            throw (new InvalidArgumentException('Storage-Mysql-Create: Invalid connection: Not a mysql connection'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return new MysqlMgr($this->storage->getLogger(), $connector);
    }
}