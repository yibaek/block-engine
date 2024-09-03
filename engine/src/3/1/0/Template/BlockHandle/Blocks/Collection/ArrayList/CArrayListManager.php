<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\ArrayList;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CArrayListManager implements IBlock
{
    public const TYPE = 'arraylist';

    private $storage;
    private $block;

    /**
     * CArrayListManager constructor.
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
            case CArrayListCreate::ACTION:
                $this->block = (new CArrayListCreate($this->storage))->setData($data);
                return $this;

            case CArrayListAdd::ACTION:
                $this->block = (new CArrayListAdd($this->storage))->setData($data);
                return $this;

            case CArrayListGet::ACTION:
                $this->block = (new CArrayListGet($this->storage))->setData($data);
                return $this;

            case CArrayListRemove::ACTION:
                $this->block = (new CArrayListRemove($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid arraylist block action[action:'.$data['action'].']');
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