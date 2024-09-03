<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver\S3;

/**
 * @since SRT-186
 */
class S3ConnectionInfo
{
    private string $region;
    private string $version;
    private string $clientKey;
    private string $clientSecret;

    public function __construct(string $region, string $clientKey, string $clientSecret, string $version = 'latest')
    {
        $this->region = $region;
        $this->clientKey = $clientKey;
        $this->clientSecret = $clientSecret;
        $this->version = $version;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getClientKey(): string
    {
        return $this->clientKey;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

}