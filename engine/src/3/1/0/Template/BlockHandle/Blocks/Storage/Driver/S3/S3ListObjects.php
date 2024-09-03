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
use Ntuple\Synctree\Util\Storage\Driver\S3\S3RequestHandler;
use Ntuple\Synctree\Util\Storage\Driver\S3\S3StorageException;

/**
 * S3 API ListObjectsV2 구현
 *
 * @since SRT-219
 */
class S3ListObjects implements IBlock
{
    use BlockHandleTrait;
    use S3BlockTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-s3-list-objects';
    public const LABEL = 'Storage-S3-List-Objects';

    /** @var int S3 API 명세에 의한 최대값 */
    public const MAX_KEYS = 1000;

    private $type;
    private $action;

    /** @var PlanStorage */
    private $storage;

    /** @var ExtraManager */
    private $extra;

    /** @var IBlock */
    private $connector;

    /** @var IBlock */
    private $bucket;

    /** @var IBlock */
    private $maxKeys;

    /** @var IBlock */
    private $continuationToken;

    /** @var BlockAggregator */
    private $parameters;


    /**
     * `ListObjectsV2`의 parameter 중에서 중요한 2개만 블럭 슬롯으로 포함한다.
     *
     * @see https://docs.aws.amazon.com/AmazonS3/latest/API/API_ListObjectsV2.html
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     * @param IBlock|null $bucket
     * @param IBlock|null $maxKeys
     * @param IBlock|null $continuationToken IsTruncated == false 인 경우, `NextContinuationToken`이 발행됨.
     *                  이 토큰을 다음 요청에서 ContinuationToken 으로 설정한다.
     * @param BlockAggregator|null $parameters additional parameters
     */
    public function __construct(
        PlanStorage $storage,
        ExtraManager $extra = null,
        IBlock $connector = null,
        IBlock $bucket = null,
        IBlock $maxKeys = null,
        IBlock $continuationToken = null,
        BlockAggregator $parameters = null)
    {
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->storage = $storage;
        $this->extra = $extra ?? new ExtraManager($storage);

        $this->connector = $connector;
        $this->bucket = $bucket;
        $this->maxKeys = $maxKeys;
        $this->continuationToken = $continuationToken;
        $this->parameters = $parameters;
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
        $this->maxKeys = $this->setBlock(
            $this->storage, $data['template']['max-keys']);
        $this->continuationToken = $this->setBlock(
            $this->storage, $data['template']['continuation-token']);
        $this->parameters = $this->setBlocks(
            $this->storage, $data['template']['parameters']);

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
                'bucket' => $this->bucket->getTemplate(),
                'max-keys' => $this->maxKeys->getTemplate(),
                'continuation-token' => $this->continuationToken->getTemplate(),
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
     * @return array
     * @throws ISynctreeException
     * @throws Exception logging failure
     */
    public function do(array &$blockStorage): array
    {
        try {
            $handle = $this->getHandle($blockStorage);
            $request = [
                'Bucket' => $this->getBucketName($handle, $blockStorage),
                'MaxKeys' => $this->getMaxKeys($blockStorage),
            ];

            $token = $this->getContinuationToken($blockStorage);
            if (null !== $token) {
                $request['ContinuationToken'] = $token;
            }

            $this->appendParameters($request, $blockStorage);

            return (new S3RequestHandler($handle->getClient()))->getObjectList($request);
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
     * @returns int value := [0, 1000]
     * @throws ISynctreeException
     */
    private function getMaxKeys(array &$blockStorage): int
    {
        if (null === $this->maxKeys) {
            return self::MAX_KEYS;
        }

        $maxKeys = $this->maxKeys->do($blockStorage);

        if (!is_int($maxKeys)) {
            throw (new InvalidArgumentException(self::LABEL.': Not integer value.'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        if ($maxKeys < 0 || $maxKeys > self::MAX_KEYS) {
            throw (new InvalidArgumentException(self::LABEL.': The value must be grater than 0, up to '.self::MAX_KEYS))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        return $maxKeys;
    }

    /**
     * @throws ISynctreeException
     */
    private function getContinuationToken(array &$blockStorage): ?string
    {
        if (null === $this->continuationToken) {
            return null;
        }

        $value = $this->continuationToken->do($blockStorage);

        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw (new InvalidArgumentException(self::LABEL.': Continuation-token is not a string value.'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        return $value;
    }

    /**
     * @param array $request
     * @param array $blockStorage
     * @return void
     */
    private function appendParameters(array &$request, array &$blockStorage): void
    {
        if (null === $this->parameters) {
            return;
        }

        /** @var IBlock $parameter */
        foreach ($this->parameters as $parameter) {
            [$key, $value] = $parameter->do($blockStorage);
            $request[$key] = $value;
        }
    }

}