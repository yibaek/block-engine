<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Share\Data;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class DataShareManager implements IBlock
{
    public const TYPE = 'share';

    private $storage;
    private $block;

    /**
     * DataShareManager constructor.
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
            case DataShare::ACTION:
                $this->block = (new DataShare($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid share block action[action:'.$data['action'].']');
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