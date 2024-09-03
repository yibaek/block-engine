<?php
namespace Ntuple\Synctree\Plan\Unit;

class AccountManager
{
    private $accountInfo;

    /**
     * AccountManager constructor.
     */
    public function __construct()
    {
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->accountInfo;
    }

    /**
     * @return int
     */
    public function getMasterID(): int
    {
        return (int) $this->accountInfo['master_id'];
    }

    /**
     * @return int
     */
    public function getSlaveID(): int
    {
        return (int) $this->accountInfo['slave_id'];
    }

    /**
     * @param array $data
     * @return $this
     */
    public function parseAccountInfo(array $data): self
    {
        $this->accountInfo = [
            'master_id' => $data['master_id'] ?? '',
            'slave_id' => $data['slave_id'] ?? ''
        ];

        return $this;
    }
}