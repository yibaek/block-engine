<?php
namespace Ntuple\Synctree\Util\Extra;

use Ntuple\Synctree\Plan\PlanStorage;

class ExtraManager
{
    private $storage;
    private $extraData;

    /**
     * ExtraManager constructor.
     * @param PlanStorage $storage
     */
    public function __construct(PlanStorage $storage)
    {
        $this->storage = $storage;
        $this->extraData = [];
    }

    /**
     * @param array $data
     * @return $this
     */
    public function setData(array $data = []): ExtraManager
    {
        $this->extraData = $data;
        return $this;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->extraData;
    }
}