<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3;

use Exception;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\StorageException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\File\Adapter\IAdapter;
use Ntuple\Synctree\Util\Storage\Driver\S3\S3RequestHandler;
use Ntuple\Synctree\Util\Storage\Driver\S3\S3StorageException;
use Throwable;


/**
 * post an object to AWS S3
 *
 * @since SRT-186
 */
class S3PutObject implements IBlock
{
    use BlockHandleTrait;
    use S3BlockTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-s3-put-object';

    /** @var string for error message */
    public const LABEL = 'Storage-S3-Put-Object';

    /** @var string */
    private $type;
    /** @var string */
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

    /** @var IBlock 원본 데이터 (bytes|adapter-local) */
    private $source;

    /** @var BlockAggregator S3 객체에 설정할 파라미터 목록 */
    private $parameters;

    /**
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     * @param IBlock|null $key
     * @param IBlock|null $source
     * @param IBlock|null $bucket overrides bucket name of connection info (optional)
     * @param BlockAggregator|null $options 추가 옵션
     */
    public function __construct(
        PlanStorage $storage,
        ExtraManager $extra = null,
        IBlock $connector = null,
        IBlock $key = null,
        IBlock $source = null,
        IBlock $bucket = null,
        BlockAggregator $options = null)
    {
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->storage = $storage;
        $this->extra = $extra ?? new ExtraManager($storage);

        $this->connector = $connector;
        $this->bucket = $bucket;
        $this->key = $key;
        $this->source = $source;
        $this->parameters = $options;
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
        $this->source = $this->setBlock($this->storage, $data['template']['source']);
        $this->parameters = $this->setBlocks($this->storage, $data['template']['parameters']);

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
                'source' => $this->source->getTemplate(),
                'parameters' => $this->getTemplateEachItem()
            ]
        ];
    }

    /**
     * @return array
     */
    private function getTemplateEachItem(): array
    {
        $resData = [];
        foreach ($this->parameters as $item) {
            $resData[] = $item->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return array [metadata]
     * @throws ISynctreeException
     * @throws Throwable
     */
    public function do(array &$blockStorage): array
    {
        try {
            $handle = $this->getHandle($blockStorage);
            $request = [
                'Bucket' => $this->getBucketName($handle, $blockStorage),
                'Key' => $this->getKey($blockStorage),
            ];
            $this->appendSource($request, $blockStorage);
            $this->applyOptions($request, $blockStorage);

            return (new S3RequestHandler($handle->getClient()))->putObject($request);
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
     * 요청 페이로드에 데이터 원본을 지정.
     * 입력이 {@link IAdapter}인 경우 user storage 하위의 파일 경로를 `SourceFile`로 사용하고,
     * 그렇지 않다면 원본 데이터 블럭의 결과를 그대로 `Body`로 사용한다.
     *
     * @param array $request
     * @param array $blockStorage
     * @throws ISynctreeException
     * @throws Throwable
     */
    private function appendSource(array &$request, array &$blockStorage): void
    {
        $source = $this->source->do($blockStorage);

        if ($source instanceof IAdapter) {
            $request['SourceFile'] = $source->getFile();
        } else {
            if (!is_string($source)) {
                throw (new InvalidArgumentException(self::LABEL.': The source is neither a string nor a resource type'))
                    ->setExceptionKey(self::TYPE, self::ACTION)
                    ->setExtraData($this->extra->getData());
            }

            $request['Body'] = $source;
        }
    }

    /**
     * @param array $request
     * @param array $blockStorage
     */
    private function applyOptions(array &$request, array &$blockStorage): void
    {
        if (null === $this->parameters) {
            return;
        }

        /** @var IBlock $option */
        foreach ($this->parameters as $option) {
            [$key, $value] = $option->do($blockStorage);
            $request[$key] = $value;
        }
    }
}