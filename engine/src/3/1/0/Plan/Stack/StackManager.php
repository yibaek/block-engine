<?php
namespace Ntuple\Synctree\Plan\Stack;

class StackManager
{
    private $stack;
    private $customUtilId;

    /**
     * StackManager constructor.
     */
    public function __construct()
    {
        $this->stack = new \Ds\Stack();
    }

    /**
     * @param Stack|null $stack
     * @return $this
     */
    public function push(Stack $stack = null): self
    {
        if ($stack && $stack->inCustmUtilScope()) {
            $this->setCustomUtilId($stack->getCustomUtilId());
        }

        $this->stack->push($stack ?? new Stack());
        return $this;
    }

    /**
     * @return Stack|null
     */
    public function peek(): ?Stack
    {
        try {
            return $this->stack->peek();
        } catch (\UnderflowException $ex) {
            return null;
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        // not in custom util scope
        if (!$this->inCustmUtilScope()) {
            return $this->stack->toArray();
        }

        // in custom util scope
        $stacks = [];
        if ($this->inCustmUtilScope()) {
            foreach ($this->stack->toArray() as $stack) {
                $stacks[] = $stack;
                if ($stack->inCustmUtilScope() && $stack->getCustomUtilId() === $this->getCustomUtilId()) {
                    break;
                }
            }
        }

        return $stacks;
    }

    /**
     * @return $this
     */
    public function pop(): self
    {
        $this->stack->pop();
        return $this;
    }

    /**
     * @param string $id
     * @return void
     */
    private function setCustomUtilId(string $id): void
    {
        $this->customUtilId = $id;
    }

    /**
     * @return string|null
     */
    private function getCustomUtilId(): ?string
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