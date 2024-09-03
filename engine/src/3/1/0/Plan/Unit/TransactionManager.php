<?php
namespace Ntuple\Synctree\Plan\Unit;

use libraries\constant\CommonConst;

class TransactionManager
{
    private $bizunit;
    private $transactionKey;

    /**
     * TransactionManager constructor.
     */
    public function __construct()
    {
        $this->bizunit = [];
        $this->transactionKey = defined('TRANSACTION_KEY') ?TRANSACTION_KEY: 'undefined';
    }

    /**
     * @param array $bizunitInfo
     * @return TransactionManager
     */
    public function setBizunit(array $bizunitInfo): self
    {
        $this->bizunit['bizunit_id'] = $bizunitInfo['plan-id'];
        $this->bizunit['bizunit_version'] = $bizunitInfo['bizunit-version'] ?? '';
        $this->bizunit['revision_id'] = $bizunitInfo['revision-id'] ?? '';
        $this->bizunit['environment'] = $bizunitInfo['plan-environment'] ?? '';

        return $this;
    }

    /**
     * @param array $planInfo
     * @return $this
     */
    public function setPlanInfo(array $planInfo): self
    {
        $this->bizunit = array_merge($this->bizunit, $planInfo);
        return $this;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return array_merge($this->bizunit, ['transaction_key' => $this->transactionKey]);
    }

    /**
     * @return string
     */
    public function getBizunitID(): string
    {
        return $this->bizunit['bizunit_id'];
    }

    /**
     * @return string
     */
    public function getBizunitVersion(): string
    {
        return $this->bizunit['bizunit_version'];
    }

    /**
     * @return string
     */
    public function getRevisionID(): string
    {
        return $this->bizunit['revision_id'];
    }

    /**
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->bizunit['environment'];
    }

    /**
     * @return int|null
     */
    public function getAppID(): ?int
    {
        return $this->bizunit['appid'] ?? null;
    }

    /**
     * @return int|null
     */
    public function getBizunitSno(): ?int
    {
        return $this->bizunit['bizunit-sno'] ?? null;
    }

    /**
     * @return int|null
     */
    public function getRevisionSno(): ?int
    {
        return $this->bizunit['revision-sno'] ?? null;
    }

    /**
     *
     * @return string
     */
    public function getTransactionKey(): string
    {
        return $this->transactionKey;
    }

    /**
     * @param array $header
     * @return bool
     */
    public function isTestMode(array $header): bool
    {
        return defined('PLAN_MODE')
            && defined('PLAN_MODE_TESTING')
            && PLAN_MODE === PLAN_MODE_TESTING
            && isset($header[strtoupper(CommonConst::PLAN_TEST_MODE_HEADER)]);
    }
}