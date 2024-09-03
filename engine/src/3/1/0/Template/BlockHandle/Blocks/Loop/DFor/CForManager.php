<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Loop\DFor;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CForManager implements IBlock
{
    public const TYPE = 'for';

    private $storage;
    private $block;

    /**
     * CForManager constructor.
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
            case CForCreate::ACTION:
                $this->block = (new CForCreate($this->storage))->setData($data);
                return $this;

            case CForSection::ACTION:
                $this->block = (new CForSection($this->storage))->setData($data);
                return $this;

            case CForCondition::ACTION:
                $this->block = (new CForCondition($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid for block action[action:'.$data['action'].']');
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