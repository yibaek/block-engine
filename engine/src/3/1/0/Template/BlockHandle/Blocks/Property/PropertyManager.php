<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Property;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class PropertyManager implements IBlock
{
    public const TYPE = 'property';

    private $storage;
    private $block;

    /**
     * PropertyManager constructor.
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
            case Property::ACTION:
                $this->block = (new Property($this->storage))->setData($data);
                return $this;

            case OperatorProperty::ACTION:
                $this->block = (new OperatorProperty($this->storage))->setData($data);
                return $this;

            case CustomUtilProperty::ACTION:
                $this->block = (new CustomUtilProperty($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid property block action[action:'.$data['action'].']');
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
     * @return OperatorProperty
     */
    public function do(array &$blockStorage): OperatorProperty
    {
        return $this->block->do($blockStorage);
    }
}