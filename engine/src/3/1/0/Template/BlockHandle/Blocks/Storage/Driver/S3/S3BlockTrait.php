<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3;

use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Util\Storage\Driver\S3\S3Handle;

/**
 * AWS S3 블럭에서 사용하는 공통 동작을 정의한다.
 *
 * @since SRT-187
 */
trait S3BlockTrait
{
    /**
     * @throws ISynctreeException
     */
    private function getHandle(array &$blockStorage): S3Handle
    {
        $handle = $this->connector->do($blockStorage);

        if (!$handle instanceof S3Handle) {
            throw (new InvalidArgumentException(self::LABEL . ': Invalid connector type'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        return $handle;
    }

    /**
     * {@link S3Handle}이나 블럭 슬롯에서 버킷 이름을 획득한다.
     *
     * @param S3Handle $handle
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getBucketName(S3Handle $handle, array &$blockStorage): string
    {
        if (null !== $this->bucket) {
            $bucketName = $this->bucket->do($blockStorage);

            if (null === $bucketName) {
                $bucketName = $handle->getBucketName();
            }
            else if (!is_string($bucketName)) {
                throw (new InvalidArgumentException(self::LABEL . ': Invalid bucket name; Not a string type'))
                    ->setExceptionKey(self::TYPE, self::ACTION)
                    ->setExtraData($this->extra->getData());
            }
        } else {
            $bucketName = $handle->getBucketName();
        }

        return $bucketName;
    }

    /**
     * object key 지정
     *
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getKey(array &$blockStorage): string
    {
        $key = $this->key->do($blockStorage);

        if (!is_string($key)) {
            throw (new InvalidArgumentException(self::LABEL.': Invalid object key; Not a string type'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        return $key;
    }

}