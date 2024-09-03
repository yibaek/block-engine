<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\Parameter;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CParameterManager implements IBlock
{
    public const TYPE = 'parameter';

    private $storage;
    private $block;

    /**
     * CParameterManager constructor.
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
            case CParameterCreate::ACTION:
                $this->block = (new CParameterCreate($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid parameter block action[action:'.$data['action'].']');
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
     * @return CParameter
     */
    public function do(array &$blockStorage): CParameter
    {
        return $this->block->do($blockStorage);
    }
}