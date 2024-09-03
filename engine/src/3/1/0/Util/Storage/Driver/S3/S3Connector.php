<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver\S3;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;

/**
 * @since SRT-186
 */
class S3Connector
{
    private $client;

    public function __construct(S3ConnectionInfo $connectionInfo)
    {
        $credential = new Credentials(
            $connectionInfo->getClientKey(),
            $connectionInfo->getClientSecret());

        $options = [
            'region' => $connectionInfo->getRegion(),
            'version' => $connectionInfo->getVersion(),
            'signature_version' => 'v4',
            'credentials' => $credential
        ];

        $this->client = new S3Client($options);
    }

    public function get(): S3Client
    {
        return $this->client;
    }

}