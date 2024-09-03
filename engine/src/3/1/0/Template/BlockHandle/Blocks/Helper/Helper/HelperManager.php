<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Helper\Helper;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class HelperManager implements IBlock
{
    public const TYPE = 'helper';

    private $storage;
    private $block;

    /**
     * HelperManager constructor.
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
            case CodeSection::ACTION:
                $this->block = (new CodeSection($this->storage))->setData($data);
                return $this;

            case Dictionary::ACTION:
                $this->block = (new Dictionary($this->storage))->setData($data);
                return $this;

            case InjectionOrigin::ACTION:
                $this->block = (new InjectionOrigin($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid helper block action[action:'.$data['action'].']');
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