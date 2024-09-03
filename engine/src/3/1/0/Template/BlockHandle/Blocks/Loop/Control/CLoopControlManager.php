<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Loop\Control;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CLoopControlManager implements IBlock
{
    public const TYPE = 'loop-control';

    private $storage;
    private $block;

    /**
     * CLoopControlManager constructor.
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
            case CBreak::ACTION:
                $this->block = (new CBreak($this->storage))->setData($data);
                return $this;

            case CContinue::ACTION:
                $this->block = (new CContinue($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid loop control block action[action:'.$data['action'].']');
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
     * @return mixed
     */
    public function do(array &$blockStorage)
    {
        return $this->block->do($blockStorage);
    }
}