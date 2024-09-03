<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Query\Transaction;

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
use Ntuple\Synctree\Util\Storage\Driver\IRDbMgr;
use Ntuple\Synctree\Util\Storage\Driver\Mysql\MysqlStorageException;
use Ntuple\Synctree\Util\Storage\Driver\Oracle\OracleStorageException;
use Ntuple\Synctree\Util\Storage\Driver\Postgres\PostgresStorageException;
use Throwable;

class Commit implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'statement-query-transaction-commit';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connector;

    /**
     * Commit constructor.
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
     * @throws ISynctreeException
     * @throws Throwable
     */
    public function do(array &$blockStorage)
    {
        try {
            // set transaction : commit
            $this->getConnector($blockStorage)->commit();
        } catch (OracleStorageException|MysqlStorageException|PostgresStorageException $ex) {
            throw (new StorageException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData())->setData($ex->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Query-Transaction-Commit'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return IRDbMgr
     * @throws ISynctreeException
     */
    private function getConnector(array &$blockStorage): IRDbMgr
    {
        $connector = $this->connector->do($blockStorage);
        if (!$connector instanceof IRDbMgr) {
            throw (new InvalidArgumentException('Storage-Query-Transaction-Commit: Invalid connector'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $connector;
    }
}