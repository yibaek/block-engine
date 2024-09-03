<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Logic\Choice;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CChoiceManager implements IBlock
{
    public const TYPE = 'choice';

    private $storage;
    private $block;

    /**
     * CChoiceManager constructor.
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
            case CIf::ACTION:
                $this->block = (new CIf($this->storage))->setData($data);
                return $this;

            case CElseif::ACTION:
                $this->block = (new CElseif($this->storage))->setData($data);
                return $this;

            case CElse::ACTION:
                $this->block = (new CElse($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid choice block action[action:'.$data['action'].']');
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