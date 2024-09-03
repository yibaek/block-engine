<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mssql;

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
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\Storage\Driver\Mssql\MssqlMgr;
use Ntuple\Synctree\Util\Storage\Driver\Mssql\MssqlStorageException;
use Throwable;

/**
 * Microsoft SQLServer connection 블럭
 *
 * @since SRT-140
 */
class MssqlCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-mssql-create';

    private const EXCEPTION_HEADER = 'Storage-MSSQL-Create';

    /** @var PlanStorage */
    private $storage;

    /** @var string  */
    private $type;

    /** @var string  */
    private $action;

    /** @var ExtraManager  */
    private $extra;

    /** @var IBlock|null  */
    private $connector;

    /** @var string */
    private $encryptionKey;

    /**
     * Microsoft SQLServer constructor.
     *
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connect
     */
    public function __construct(
        PlanStorage $storage,
        ExtraManager $extra = null,
        IBlock $connect = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->connector = $connect;

        $this->encryptionKey = $storageEncryptKey ?? CommonUtil::getStorageEncryptKey();
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
     * @return MssqlMgr
     * @throws SynctreeInnerException|ISynctreeException
     * @throws Exception Logging failure
     */
    public function do(array &$blockStorage): MssqlMgr
    {
        try {
            return $this->getConnector($blockStorage);
        } catch (MssqlStorageException $ex) {
            throw (new StorageException($ex->getMessage(), 0, $ex))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException(self::EXCEPTION_HEADER, 0, $ex))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());

        }
    }

    /**
     * 커넥터 블럭에서 연결 정보를 획득하여 {@link IRdbMgr} 생성
     *
     * @param array $blockStorage
     * @return MssqlMgr
     * @throws ISynctreeException
     */
    private function getConnector(array &$blockStorage): MssqlMgr
    {
        $connector = $this->connector->do($blockStorage);

        if (empty($connector) || !is_array($connector)) {
            throw (new InvalidArgumentException(self::EXCEPTION_HEADER.': Invalid connection'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setContext((new InvalidArgumentExceptionContext())
                    ->setExpected('array')
                    ->setActual(gettype($connector))
                )
                ->setExtraData($this->extra->getData());

        }

        if (MssqlMgr::DRIVER_NAME !== $connector['driver']) {
            throw (new InvalidArgumentException(self::EXCEPTION_HEADER.': Invalid connection: Not a SQLServer connection'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setContext((new InvalidArgumentExceptionContext())
                    ->setExpected(MssqlMgr::DRIVER_NAME)
                    ->setActual($connector['driver'])
                )
                ->setExtraData($this->extra->getData());

        }

        return new MssqlMgr($this->storage->getLogger(), $connector);
    }
}