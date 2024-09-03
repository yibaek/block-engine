<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver\S3;

use Aws\S3\S3Client;

/**
 * {@link S3Client}와 `bucket name`을 관리하는 VO
 *
 * @since SRT-187
 */
class S3Handle
{
    /** @var S3Client */
    private $client;

    /** @var string */
    private $bucketName;

    /**
     * @param S3Client $client
     * @param string $bucketName
     */
    public function __construct(S3Client $client, string $bucketName)
    {
        $this->client = $client;
        $this->bucketName = $bucketName;
    }

    public function getClient(): S3Client
    {
        return $this->client;
    }

    public function getBucketName(): string
    {
        return $this->bucketName;
    }
}