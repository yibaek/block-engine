<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Logic;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CLogicManager implements IBlock
{
    public const TYPE = 'logic';

    private $storage;
    private $block;

    /**
     * CLogicManager constructor.
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
            case CLogicCreate::ACTION:
                $this->block = (new CLogicCreate($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid logic block action[action:'.$data['action'].']');
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