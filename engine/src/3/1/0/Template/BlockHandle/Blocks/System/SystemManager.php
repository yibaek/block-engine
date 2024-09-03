<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\System;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\System\File\Move;
use Ntuple\Synctree\Template\BlockHandle\Blocks\System\Process\MicroSleep;
use Ntuple\Synctree\Template\BlockHandle\Blocks\System\Process\Sleep;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class SystemManager implements IBlock
{
    public const TYPE = 'system';

    private $storage;
    private $block;

    /**
     * SystemManager constructor.
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
            case Move::ACTION:
                $this->block = (new Move($this->storage))->setData($data);
                return $this;

            case Sleep::ACTION:
                $this->block = (new Sleep($this->storage))->setData($data);
                return $this;

            case MicroSleep::ACTION:
                $this->block = (new MicroSleep($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid system block action[action:'.$data['action'].']');
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