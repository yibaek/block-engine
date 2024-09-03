<?php
namespace domains\proxy\log\entities;

/**
 * shard 용 데이터베이스 connection 정보
 * 
 * 전형적으로 `synctree_studio_logdb.proxy_api_log`
 */
class ProxyLog
{
    /**
     * @var int
     */
    private $bizunit_proxy_id;

    /**
     * @see https://redmine.nntuple.com/issues/6176
     * @var string
     */
    private $transaction_key;

    /**
     * @var int
     */
    private $bizunit_sno;

    /**
     * @var string
     */
    private $bizunit_id;

    /**
     * @var string
     */
    private $bizunit_version;

    /**
     * @var int
     */
    private $revision_sno;

    /**
     * @var string
     */
    private $revision_id;

    /**
     * @var string
     */
    private $revision_environment = 'production';

    /**
     * @var float
     */
    private $latency;

    /**
     * @var int
     */
    private $size;

    /**
     * @var int
     */
    private $response_status;

    /**
     * @var string Y-m-d H:i:s
     */
    private $register_date;

    /**
     * @var string Y-m-d H:i:s
     */
    private $timestamp_date;

    /**
     * @var null|int
     */
    private $portalAppId;

    /**
     * @return int
     */
    public function getBizunitProxyId(): int
    {
        return $this->bizunit_proxy_id;
    }

    /**
     * @param int $bizunit_proxy_id
     */
    public function setBizunitProxyId(int $bizunit_proxy_id): void
    {
        $this->bizunit_proxy_id = $bizunit_proxy_id;
    }

    /**
     * @return string
     */
    public function getTransactionKey(): string
    {
        return $this->transaction_key;
    }

    /**
     * @param string $transaction_key
     */
    public function setTransactionKey(string $transaction_key): void
    {
        $this->transaction_key = $transaction_key;
    }

    /**
     * @return int
     */
    public function getBizunitSno(): int
    {
        return $this->bizunit_sno;
    }

    /**
     * @param int $bizunit_sno
     */
    public function setBizunitSno(int $bizunit_sno): void
    {
        $this->bizunit_sno = $bizunit_sno;
    }

    /**
     * @return string
     */
    public function getBizunitId(): string
    {
        return $this->bizunit_id;
    }

    /**
     * @param string $bizunit_id
     */
    public function setBizunitId(string $bizunit_id): void
    {
        $this->bizunit_id = $bizunit_id;
    }

    /**
     * @return string
     */
    public function getBizunitVersion(): string
    {
        return $this->bizunit_version;
    }

    /**
     * @param string $bizunit_version
     */
    public function setBizunitVersion(string $bizunit_version): void
    {
        $this->bizunit_version = $bizunit_version;
    }

    /**
     * @return int
     */
    public function getRevisionSno(): int
    {
        return $this->revision_sno;
    }

    /**
     * @param int $revision_sno
     */
    public function setRevisionSno(int $revision_sno): void
    {
        $this->revision_sno = $revision_sno;
    }

    /**
     * @return string
     */
    public function getRevisionId(): string
    {
        return $this->revision_id;
    }

    /**
     * @param string $revision_id
     */
    public function setRevisionId(string $revision_id): void
    {
        $this->revision_id = $revision_id;
    }

    /**
     * @return string
     */
    public function getRevisionEnvironment(): string
    {
        return $this->revision_environment;
    }

    /**
     * @param string $revision_environment
     */
    public function setRevisionEnvironment(string $revision_environment): void
    {
        $this->revision_environment = $revision_environment;
    }

    /**
     * @return float
     */
    public function getLatency(): float
    {
        return $this->latency;
    }

    /**
     * @param float $latency
     */
    public function setLatency(float $latency): void
    {
        $this->latency = $latency;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    /**
     * @return int
     */
    public function getResponseStatus(): int
    {
        return $this->response_status;
    }

    /**
     * @param int $response_status
     */
    public function setResponseStatus(int $response_status): void
    {
        $this->response_status = $response_status;
    }

    /**
     * @return string
     */
    public function getRegisterDate(): string
    {
        return $this->register_date;
    }

    /**
     * @param string $register_date
     */
    public function setRegisterDate(string $register_date): void
    {
        $this->register_date = $register_date;
    }

    /**
     * @return string
     */
    public function getTimestampDate(): string
    {
        return $this->timestamp_date;
    }

    /**
     * @param string $timestamp_date
     */
    public function setTimestampDate(string $timestamp_date): void
    {
        $this->timestamp_date = $timestamp_date;
    }

    /**
     * @param int|null $appID
     */
    public function setPortalAppID(int $appID = null): void
    {
        $this->portalAppId = $appID;
    }

    /**
     * @return int|null
     */
    public function getPortalAppID(): ?int
    {
        return $this->portalAppId;
    }
}