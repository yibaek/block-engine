<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Logic\Expression\Condition;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CConditionManager implements IBlock
{
    public const TYPE = 'condition';

    private $storage;
    private $block;

    /**
     * CConditionManager constructor.
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
            case CConditionCreate::ACTION:
                $this->block = (new CConditionCreate($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid condition block action[action:'.$data['action'].']');
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
     * @return string
     */
    public function do(array &$blockStorage): string
    {
        return $this->block->do($blockStorage);
    }
}