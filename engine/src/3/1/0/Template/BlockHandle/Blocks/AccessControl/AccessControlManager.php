<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\AccessControl;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\AccessControl\RateLimit\RateLimit;
use Ntuple\Synctree\Template\BlockHandle\Blocks\AccessControl\Throttle\Throttle;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class AccessControlManager implements IBlock
{
    public const TYPE = 'access-control';

    private $storage;
    private $block;

    /**
     * AccessControlManager constructor.
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
            case RateLimit::ACTION:
                $this->block = (new RateLimit($this->storage))->setData($data);
                return $this;

            case Throttle::ACTION:
                $this->block = (new Throttle($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid access-control block action[action:'.$data['action'].']');
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