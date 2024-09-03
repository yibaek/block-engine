<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3;

use Aws\Credentials\Credentials;
use Exception;
use JsonException;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

/**
 * @since SRT-186
 */
class S3Connector implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-s3-connect-create';

    private const JSON_DEPTH = 512;
    private const S3_API_VERSION = '2006-03-01';
    private const S3_TYPE = 'awss3';
    private const LABEL = 'Storage-S3-Connector';

    /** @var PlanStorage */
    private $storage;

    /** @var ExtraManager */
    private $extra;

    /** @var string */
    private $type;

    /** @var string  */
    private $action;

    /** @var IBlock|null */
    private $connectID;

    /** @var string */
    private $encryptKey;

    /**
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     * @param string|null $encryptKey for unit test. 기본적으로는 config 파일에서 가져옴.
     */
    public function __construct(
        PlanStorage $storage,
        ExtraManager $extra = null,
        IBlock $connector = null,
        string $encryptKey = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);

        $this->connectID = $connector;
        $this->encryptKey = $encryptKey ?? CommonUtil::getStorageEncryptKey();
    }

    /**
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
     * @throws Exception
     * @throws ISynctreeException
     */
    public function do(array &$blockStorage): array
    {
        try {
            return $this->getConnectInfo($blockStorage);
        } catch (SynctreeException | SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE . ':' . self::ACTION);
            throw (new RuntimeException(self::LABEL))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }
    }

    /**
     * connectID 슬롯의 null 여부는 `studio`에서 검증되므로 `engine`에서 재차 확인하지 않음.
     *
     * @param array $blockStorage
     * @return array [connection info[], bucket name]
     * @throws Throwable
     * @throws ISynctreeException
     */
    private function getConnectInfo(array &$blockStorage): array
    {
        $storageData = $this->storage->getRdbStudioResource()->getHandler()
            ->executeGetStorageDetail(
                (int)$this->connectID->do($blockStorage),
                $this->encryptKey);

        $connInfo = $this->getStorageConnectInfo($storageData['storage_db_info']);

        if (self::S3_TYPE !== $connInfo['storage_type']) {
            throw (new InvalidArgumentException(self::LABEL . ': Invalid storage type'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        return [
            [
                'version' => $connInfo['storage_version'] ?? self::S3_API_VERSION,
                'region' => $connInfo['storage_region'] ?? '',
                'credentials' => [
                    'key' => $connInfo['storage_key'] ?? '',
                    'secret' => $connInfo['storage_secret'] ?? ''
                ],
            ],
            $connInfo['storage_bucket'] ?? '',
        ];
    }

    /**
     * @param string $connectInfo
     * @return array
     * @throws JsonException
     */
    private function getStorageConnectInfo(string $connectInfo): array
    {
        return json_decode(
            $connectInfo,
            true,
            self::JSON_DEPTH,
            JSON_THROW_ON_ERROR);
    }
}