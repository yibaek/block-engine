<?php
namespace Ntuple\Synctree\Plan\Stack;

class Stack
{
    public $data;
    private $customUtilId;

    /**
     * Stack constructor.
     */
    public function __construct(array $initData = [])
    {
        $this->data = $initData;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getData(string $key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setCustomUtilId(string $id): self
    {
        $this->customUtilId = $id;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCustomUtilId(): ?string
    {
        return $this->customUtilId;
    }

    /**
     * @return bool
     */
    public function inCustmUtilScope(): bool
    {
        return null !== $this->customUtilId;
    }
}