<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class ProtocolManager implements IBlock
{
    public const TYPE = 'protocol';

    private $storage;
    private $block;

    /**
     * ProtocolManager constructor.
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
            case ProtocolCreate::ACTION:
                $this->block = (new ProtocolCreate($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid protocol block action[action:'.$data['action'].']');
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