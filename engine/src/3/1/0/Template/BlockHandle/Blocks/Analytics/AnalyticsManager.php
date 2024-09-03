<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Analytics;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class AnalyticsManager implements IBlock
{
    public const TYPE = 'analytics';

    private $storage;
    private $block;

    /**
     * AnalyticsManager constructor.
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
            case Event::ACTION:
                $this->block = (new Event($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid analytics block action[action:'.$data['action'].']');
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