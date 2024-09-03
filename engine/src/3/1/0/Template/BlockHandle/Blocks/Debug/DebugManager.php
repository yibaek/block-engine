<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Debug;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class DebugManager implements IBlock
{
    public const TYPE = 'debug';

    private $storage;
    private $block;

    /**
     * DebugManager constructor.
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
            case BreakPoint::ACTION:
                $this->block = (new BreakPoint($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid debug block action[action:'.$data['action'].']');
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