<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\Pair;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CPairManager implements IBlock
{
    public const TYPE = 'pair';

    private $storage;
    private $block;

    /**
     * CPairManager constructor.
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
            case CPairCreate::ACTION:
                $this->block = (new CPairCreate($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid pair block action[action:'.$data['action'].']');
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
    public function do(array &$blockStorage): array
    {
        return $this->block->do($blockStorage);
    }
}