<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Variable;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class VariableManager implements IBlock
{
    public const TYPE = 'variable';

    private $storage;
    private $block;

    /**
     * VariableManager constructor.
     * @param PlanStorage $storage
     * @param IBlock|null $block
     */
    public function __construct(PlanStorage $storage, IBlock $block = null)
    {
        $this->storage = $storage;
        $this->block = $block;
    }

    /**
     * @param array $data
     * @return IBlock
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        switch ($data['action']) {
            case VariableCreate::ACTION:
                $this->block = (new VariableCreate($this->storage))->setData($data);
                return $this;

            case VariableSet::ACTION:
                $this->block = (new VariableSet($this->storage))->setData($data);
                return $this;

            case VariableGet::ACTION:
                $this->block = (new VariableGet($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid variable block action[action:'.$data['action'].']');
        }
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return $this->block->getTemplate();
    }

    /**
     * @param array $blockStorage
     */
    public function do(array &$blockStorage)
    {
        return $this->block->do($blockStorage);
    }
}