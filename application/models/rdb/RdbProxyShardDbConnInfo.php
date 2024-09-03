<?php
namespace models\rdb;

class RdbProxyShardDbConnInfo implements IRdbProxyShardDbConnInfo
{
    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $port;

    /**
     * @var string
     */
    protected $username;

    public function __construct(string $host = 'localhost', string $port = '3306', ?string $username = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
    }
    public function getHost(): string
    {
        return $this->host;
    }
    public function getPort(): string
    {
        return $this->port;
    }
    public function getUsername(): ?string
    {
        return $this->username;
    }
}