<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mssql;

use Throwable;
use Exception;
use JsonException;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\Extra\ExtraManager;

/**
 * @since SRT-140
 */
class MssqlConnector implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-mssql-connect-create';

    /** @var PlanStorage */
    private $storage;

    /** @var string  */
    private $type;

    /** @var string  */
    private $action;

    /** @var ExtraManager  */
    private $extra;

    /** @var IBlock|null  */
    private $connectID;

    /** @var string */
    private $encryptionKey;

    /**
     * Microsoft SQLServer Connector constructor.
     *
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connectID
     * @param string|null $storageEncryptKey
     */
    public function __construct(
        PlanStorage $storage,
        ExtraManager $extra = null,
        IBlock $connectID = null,
        string $storageEncryptKey = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->connectID = $connectID;

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
        $this->connectID = $this->setBlock($this->storage, $data['template']['connect-id']);

        return $this;
    }

    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'extra' => $this->extra->getData(),
            'template' => [
                'connect-id' => $this->connectID->getTemplate()
            ]
        ];
    }

    /**
     * @throws ISynctreeException
     * @throws Exception
     */
    public function do(array &$blockStorage): array
    {
        try {
            return $this->getConnectInfo($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Mssql-Connector'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws Throwable
     */
    private function getConnectInfo(array &$blockStorage): array
    {
        $storageData = $this->storage->getRdbStudioResource()
            ->getHandler()
            ->executeGetStorageDetail(
                (int)$this->connectID->do($blockStorage),
                $this->encryptionKey);

        $connectInfo = $this->getStorageConnectInfo($storageData['storage_db_info']);

        return [
            'driver' => $storageData['storage_type'],
            'host' => $connectInfo['storage_host'],
            'port' => $connectInfo['storage_port'],
            'dbname' => $connectInfo['storage_dbname'],
            'charset' => $connectInfo['storage_charset'],
            'username' => $connectInfo['storage_username'],
            'password' => $connectInfo['storage_password'],
            'options' => $connectInfo['storage_options'] ?? null,
            'ssl' => null
        ];
    }

    /**
     * @param string $connectInfo
     * @return array
     * @throws JsonException
     */
    private function getStorageConnectInfo(string $connectInfo): array
    {
        return json_decode($connectInfo, true, 512, JSON_THROW_ON_ERROR);
    }
}