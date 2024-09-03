<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Custom\Util;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CustomUtilManager implements IBlock
{
    public const TYPE = 'custom-util';

    private $storage;
    private $block;

    /**
     * VariableManager constructor.
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
            case CustomUtilCreate::ACTION:
                $this->block = (new CustomUtilCreate($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid custom-util block action[action:'.$data['action'].']');
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