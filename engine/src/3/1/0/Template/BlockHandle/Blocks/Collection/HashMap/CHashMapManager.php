<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\HashMap;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CHashMapManager implements IBlock
{
    public const TYPE = 'hashmap';

    private $storage;
    private $block;

    /**
     * CHashMapManager constructor.
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
            case CHashMapCreate::ACTION:
                $this->block = (new CHashMapCreate($this->storage))->setData($data);
                return $this;

            case CHashMapAdd::ACTION:
                $this->block = (new CHashMapAdd($this->storage))->setData($data);
                return $this;

            case CHashMapGet::ACTION:
                $this->block = (new CHashMapGet($this->storage))->setData($data);
                return $this;

            case CHashMapRemove::ACTION:
                $this->block = (new CHashMapRemove($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid hashmap block action[action:'.$data['action'].']');
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