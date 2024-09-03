<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Loop\DForeach;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CForeachManager implements IBlock
{
    public const TYPE = 'foreach';

    private $storage;
    private $block;

    /**
     * CForeachManager constructor.
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
            case CForeachCreate::ACTION:
                $this->block = (new CForeachCreate($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid foreach block action[action:'.$data['action'].']');
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
     * @return array
     */
    public function do(array &$blockStorage): ?array
    {
        return $this->block->do($blockStorage);
    }
}