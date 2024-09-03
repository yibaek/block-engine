<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Oracle;

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
use Ntuple\Synctree\Util\Storage\Driver\Oracle\OracleHandler;
use Ntuple\Synctree\Util\Storage\Driver\Oracle\OracleMgr;
use Ntuple\Synctree\Util\Storage\Driver\Oracle\OracleStorageException;
use Throwable;

class OracleExecuteQuery implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-oracle-execute-query';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connector;
    private $query;

    /**
     * OracleExecuteQuery constructor.
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
     * @return array|int
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage)
    {
        try {
            [$query, $param] = $this->getQuery($blockStorage);

            // execute query
            return (new OracleHandler($this->getConnector($blockStorage)))->executeQuery($query, $param);
        } catch (OracleStorageException $ex) {
            throw (new StorageException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData())->setData($ex->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Oracle-Execute-Query'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return OracleMgr
     * @throws ISynctreeException
     */
    private function getConnector(array &$blockStorage): OracleMgr
    {
        $connector = $this->connector->do($blockStorage);
        if (!$connector instanceof OracleMgr) {
            throw (new InvalidArgumentException('Storage-Oracle-Execute-Query: Invalid connector: Not a oracle connector'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Storage-Oracle-Execute-Query: Invalid query: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $query;
    }
}