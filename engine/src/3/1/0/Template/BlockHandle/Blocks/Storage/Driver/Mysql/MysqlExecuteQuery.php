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

class MysqlExecuteQuery implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-mysql-execute-query';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connector;
    private $query;

    /**
     * MysqlExecuteQuery constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     * @param IBlock|null $query
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $connector = null, IBlock $query = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->connector = $connector;
        $this->query = $query;
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
        $this->query = $this->setBlock($this->storage, $data['template']['query']);

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
                'query' => $this->query->getTemplate()
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
            [$query, $param] = $this->getQuery($blockStorage);

            // execute query
            return (new MysqlHandler($this->getConnector($blockStorage)))->executeQuery($query, $param);
        } catch (MysqlStorageException $ex) {
            throw (new StorageException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Mysql-Execute-Query'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Storage-Mysql-Execute-Query: Invalid connector: Not a mysql connector'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $connector;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getQuery(array &$blockStorage): array
    {
        $query = $this->query->do($blockStorage);
        if (!is_array($query)) {
            throw (new InvalidArgumentException('Storage-Mysql-Execute-Query: Invalid query: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $query;
    }
}