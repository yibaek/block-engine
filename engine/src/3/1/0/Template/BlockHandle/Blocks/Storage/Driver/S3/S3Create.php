<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3;

use Aws\S3\S3Client;
use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\Storage\Driver\S3\S3Handle;
use Throwable;

/**
 * @since SRT-186
 */
class S3Create implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-s3-create';
    public const LABEL = 'Storage-S3-Create-Client';

    /** @var string 변경되는 일이 없을 것으로 예상되므로 S3 버전 정보는 상수로 취급한다. */
    public const S3_LATEST_VERSION = '2006-03-01';

    private $type;
    private $action;

    /** @var PlanStorage */
    private $storage;

    /** @var ExtraManager */
    private $extra;

    /** @var IBlock */
    private $connector;

    /**
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
                'connector' => $this->connector->getTemplate(),
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return mixed
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): S3Handle
    {
        try {
            [$connInfo, $bucket] = $this->getConnectionInfo($blockStorage);
            return new S3Handle(new S3Client($connInfo), $bucket);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException(self::LABEL))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return array [connectionInfo, bucketName]
     * @throws ISynctreeException
     */
    private function getConnectionInfo(array &$blockStorage): array
    {
        [$connectionInfo, $bucket] = $this->connector->do($blockStorage);

        if (!is_array($connectionInfo)) {
            throw (new InvalidArgumentException(self::LABEL.': Invalid connector'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        if (!is_string($bucket)) {
            throw (new InvalidArgumentException(self::LABEL.': Invalid connection info; no bucket name.'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        if (!array_key_exists('version', $connectionInfo)) {
            $connectionInfo['version'] = self::S3_LATEST_VERSION;
        }

        return [$connectionInfo, $bucket];
    }
}