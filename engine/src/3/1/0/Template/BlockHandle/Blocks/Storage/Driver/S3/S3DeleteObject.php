<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3;

use Exception;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\StorageException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\Storage\Driver\S3\S3RequestHandler;
use Ntuple\Synctree\Util\Storage\Driver\S3\S3StorageException;


/**
 * @since SRT-186
 */
class S3DeleteObject implements IBlock
{
    use BlockHandleTrait;
    use S3BlockTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-s3-delete-object';
    public const LABEL = 'Storage-S3-Delete-Object';

    private $type;
    private $action;

    /** @var PlanStorage */
    private $storage;

    /** @var ExtraManager */
    private $extra;

    /** @var IBlock */
    private $connector;

    /** @var IBlock  */
    private $bucket;

    /** @var IBlock  */
    private $key;

    /**
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     * @param IBlock|null $bucket overrides bucket name of connection info (optional)
     * @param IBlock|null $key     */
    public function __construct(
        PlanStorage $storage,
        ExtraManager $extra = null,
        IBlock $connector = null,
        IBlock $key = null,
        IBlock $bucket = null)
    {
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->storage = $storage;
        $this->extra = $extra ?? new ExtraManager($storage);

        $this->connector = $connector;
        $this->bucket = $bucket;
        $this->key = $key;
    }

    /**
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);

        $this->connector = $this->setBlock($this->storage, $data['template']['connector']);
        $this->bucket = $this->setBlock($this->storage, $data['template']['bucket']);
        $this->key = $this->setBlock($this->storage, $data['template']['key']);

        return $this;
    }

    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'extra' => $this->extra->getData(),
            'template' => [
                'connector' => $this->connector->getTemplate(),
                'key' => $this->key->getTemplate(),
                'bucket' => $this->bucket->getTemplate(),
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array [metadata]
     * @throws ISynctreeException
     * @throws Exception
     */
    public function do(array &$blockStorage): array
    {
        try {
            $handle = $this->getHandle($blockStorage);
            $request = [
                'Bucket' => $this->getBucketName($handle, $blockStorage),
                'Key' => $this->getKey($blockStorage)
            ];

            return (new S3RequestHandler($handle->getClient()))->deleteObject($request);
        } catch (S3StorageException $ex) {
            throw (new StorageException($ex->getMessage()))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        } catch (SynctreeException|ISynctreeException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException(self::LABEL))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }
    }
}