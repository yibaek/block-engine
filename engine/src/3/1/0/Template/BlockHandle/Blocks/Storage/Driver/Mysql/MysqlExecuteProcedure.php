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
use Ntuple\Synctree\Util\Storage\Driver\Mysql\MysqlHandler;
use Ntuple\Synctree\Util\Storage\Driver\Mysql\MysqlMgr;
use Ntuple\Synctree\Util\Storage\Driver\Mysql\MysqlStorageException;
use Throwable;

class MysqlExecuteProcedure implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-mysql-execute-procedure';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connector;
    private $procedure;

    /**
     * MysqlExecuteProcedure constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     * @param IBlock|null $procedure
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $connector = null, IBlock $procedure = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->connector = $connector;
        $this->procedure = $procedure;
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
        $this->procedure = $this->setBlock($this->storage, $data['template']['procedure']);

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
                'procedure' => $this->procedure->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return mixed
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage)
    {
        try {
            // get procedure
            [$procedure, $param] = $this->getProcedure($blockStorage);

            // execute procedure
            return (new MysqlHandler($this->getConnector($blockStorage)))->executeProcedure($procedure, $param);
        } catch (MysqlStorageException $ex) {
            throw (new StorageException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Mysql-Execute-Procedure'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
        if (!$connector instanceof MysqlMgr) {
            throw (new InvalidArgumentException('Storage-Mysql-Execute-Procedure: Invalid connector: Not a mysql connector'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $connector;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getProcedure(array &$blockStorage): array
    {
        $procedure = $this->procedure->do($blockStorage);
        if (!is_array($procedure)) {
            throw (new InvalidArgumentException('Storage-Mysql-Execute-Procedure: Invalid procedure: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $procedure;
    }
}