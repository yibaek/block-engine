<?php
namespace Ntuple\Synctree\Plan\Unit;

class AuthDataManager
{
    private $extraData;

    /**
     * AuthDataManager constructor.
     */
    public function __construct()
    {
       $this->extraData = [];
    }

    /**
     * @param array $data
     * @return AuthDataManager
     */
    public function setExtraData(array $data): self
    {
        $this->extraData = $data;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getVerifyAppID(): ?int
    {
        return $this->extraData['certification_match']['app_id'] ?? null;
    }
}