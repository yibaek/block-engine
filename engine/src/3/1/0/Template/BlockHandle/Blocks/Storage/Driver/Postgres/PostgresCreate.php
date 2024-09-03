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
use Ntuple\Synctree\Models\Rdb\IRdbMgr;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\Storage\Driver\Postgres\PostgresMgr;
use Ntuple\Synctree\Util\Storage\Driver\Postgres\PostgresStorageException;
use Throwable;


/**
 * PostgresMgr 생성 블럭.
 * {@link PostgresConnector::ACTION}만 보면 {@link PostgresConnector}이 연결을 생성하는 것 같지만 실제로는 이 블럭이 커넥션을 생성한다.
 * 기존 코드의 naming convention 유지를 위해 ACTION 값을 서로 바꾸지 않음.
 *
 * @since SYN-389
 */
class PostgresCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-postgresql-create';

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

    /**
     * PostgresCreate constructor.
     *
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     */
    public function __construct(
        PlanStorage $storage,
        ExtraManager $extra = null,
        IBlock $connector = null)
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
     * @return PostgresMgr
     * @throws SynctreeInnerException|ISynctreeException
     * @throws Exception Logging failure
     */
    public function do(array &$blockStorage): PostgresMgr
    {
        try {
            return $this->getConnector($blockStorage);
        } catch (PostgresStorageException $ex) {
            throw (new StorageException($ex->getMessage(), 0, $ex))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Postgres-Create', 0, $ex))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }
    }


    /**
     * 커넥터 블럭에서 연결 정보를 획득하여 {@link IRdbMgr} 생성
     *
     * @param array $blockStorage
     * @return PostgresMgr
     * @throws ISynctreeException
     */
    private function getConnector(array &$blockStorage): PostgresMgr
    {
        $connector = $this->connector->do($blockStorage);

        if (empty($connector) || !is_array($connector)) {
            throw (new InvalidArgumentException('Storage-Postgresql-Create: Invalid connection'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setContext((new InvalidArgumentExceptionContext())
                    ->setExpected('array')
                    ->setActual(gettype($connector))
                )
                ->setExtraData($this->extra->getData());
        }

        if (PostgresMgr::DRIVER_NAME !== $connector['driver']) {
            throw (new InvalidArgumentException('Storage-Postgresql-Create: Invalid connection: Not a postgres connection'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setContext((new InvalidArgumentExceptionContext())
                    ->setExpected(PostgresMgr::DRIVER_NAME)
                    ->setActual($connector['driver'])
                )
                ->setExtraData($this->extra->getData());
        }

        return new PostgresMgr($this->storage->getLogger(), $connector);
    }
}