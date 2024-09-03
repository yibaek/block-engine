<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3;

use Exception;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\StorageException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\File\Adapter\IAdapter;
use Ntuple\Synctree\Util\Storage\Driver\S3\S3RequestHandler;
use Ntuple\Synctree\Util\Storage\Driver\S3\S3StorageException;

/**
 * @since SRT-186
 */
class S3GetObject implements IBlock
{
    use BlockHandleTrait;
    use S3BlockTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-s3-get-object';
    public const LABEL = 'Storage-S3-Get-Object';

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
     * @var IBlock|null 다운로드한 데이터를 파일로 저장할 경로를 지정.
     *                  {@link IAdapter}를 반환하는 블럭이어야 함.
     */
    private $destination;

    /**
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     * @param IBlock|null $key
     * @param IBlock|null $bucket overrides bucket name of connection info (optional)
     * @param IBlock|null $destination 다운로드한 데이터를 파일로 저장할 경로를 지정. (optional)
     */
    public function __construct(
        PlanStorage $storage,
        ExtraManager $extra = null,
        IBlock $connector = null,
        IBlock $key = null,
        IBlock $bucket = null,
        IBlock $destination = null)
    {
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->storage = $storage;
        $this->extra = $extra ?? new ExtraManager($storage);

        $this->connector = $connector;
        $this->bucket = $bucket;
        $this->key = $key;
        $this->destination = $destination;
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
        $this->key = $this->setBlock($this->storage, $data['template']['key']);
        $this->bucket = $this->setBlock($this->storage, $data['template']['bucket']);
        $this->destination = $this->setBlock($this->storage, $data['template']['destination']);

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
                'destination' => $this->destination->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array ['metadata' => array, ('body' => {value})]
     * @throws ISynctreeException
     * @throws Exception logging failure
     */
    public function do(array &$blockStorage): array
    {
        try {
            $handle = $this->getHandle($blockStorage);
            $request = [
                'Bucket' => $this->getBucketName($handle, $blockStorage),
                'Key' => $this->getKey($blockStorage)
            ];

            $saveAs = $this->getDestination($blockStorage);
            if ($saveAs) {
                $request['SaveAs'] = $saveAs;
            }

            return (new S3RequestHandler($handle->getClient()))->getObject($request);
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

    /**
     * 파일의 다운로드 경로를 지정
     * 
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getDestination(array &$blockStorage): ?string
    {
        if (null === $this->destination) {
            return null;
        }

        $dest = $this->destination->do($blockStorage);

        if (null === $dest) {
            return null;
        }
        else if (!$dest instanceof IAdapter) {
            throw (new InvalidArgumentException(self::LABEL.': Invalid file destination: Not a file adapter'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        return $dest->getFile();
    }
}