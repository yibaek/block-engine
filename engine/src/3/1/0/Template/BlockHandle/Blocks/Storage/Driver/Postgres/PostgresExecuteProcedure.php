<?php declare(strict_types=1);

namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Postgres;

use Exception;
use Ntuple\Synctree\Exceptions\Contexts\InvalidArgumentExceptionContext;
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
use Ntuple\Synctree\Util\Storage\Driver\Postgres\PostgresHandler;
use Ntuple\Synctree\Util\Storage\Driver\Postgres\PostgresMgr;
use Ntuple\Synctree\Util\Storage\Driver\Postgres\PostgresStorageException;
use Throwable;


/**
 * Block, executes Postgres stored procedure
 *
 * @since SYN-389
 */
class PostgresExecuteProcedure implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-postgresql-execute-procedure';

    /** @var PlanStorage */
    private $storage;

    /** @var string */
    private $type;

    /** @var string */
    private $action;

    /** @var ExtraManager */
    private $extra;

    /** @var IBlock|null */
    private $connector;

    /** @var IBlock|null */
    private $procedure;

    /**
     * PostgresExecuteProcedure constructor.
     *
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     * @param IBlock|null $procedure
     */
    public function __construct(
        PlanStorage $storage,
        ExtraManager $extra = null,
        IBlock $connector = null,
        IBlock $procedure = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->connector = $connector;
        $this->procedure = $procedure;
    }

    /**
     * Setup block parameters
     *
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
     * @throws SynctreeInnerException|ISynctreeException
     * @throws Exception Logging failure
     */
    public function do(array &$blockStorage)
    {
        try {
            // get procedure
            [$procedure, $param] = $this->getProcedure($blockStorage);

            // execute procedure
            return (new PostgresHandler($this->getConnector($blockStorage)))
                ->executeProcedure($procedure, $param);
        } catch (PostgresStorageException $ex) {
            throw (new StorageException($ex->getMessage(), 0, $ex))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Postgres-Execute-Procedure', 0, $ex))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return PostgresMgr
     * @throws ISynctreeException
     */
    private function getConnector(array &$blockStorage): PostgresMgr
    {
        if (!$this->connector) {
            throw (new InvalidArgumentException('Storage-Postgres-Execute-Procedure: Missing connector block'))
                ->setContext((new InvalidArgumentExceptionContext())
                    ->setExpected('PostgreSQL connector')
                    ->setActual(gettype($this->connector))
                )
                ->setExtraData($this->extra->getData());
        }

        $connector = $this->connector->do($blockStorage);

        if (!$connector instanceof PostgresMgr) {
            throw (new InvalidArgumentException('Storage-Postgres-Execute-Procedure: Not a postgres connector'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setContext((new InvalidArgumentExceptionContext())
                    ->setExpected(PostgresMgr::class)
                    ->setActual(gettype($connector))
                )
                ->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Storage-Postgres-Execute-Procedure: Invalid PostgreSQL procedure block'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setContext((new InvalidArgumentExceptionContext())
                    ->setExpected('array')
                    ->setActual(gettype($procedure))
                )
                ->setExtraData($this->extra->getData());
        }

        return $procedure;
    }
}