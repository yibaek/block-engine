<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Unit\Soap;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class SoapRepresentsManager implements IBlock
{
    public const TYPE = 'soap-represents';

    private $storage;
    private $block;

    /**
     * SoapRepresentsManager constructor.
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
            case SoapHeaderCreate::ACTION:
                $this->block = (new SoapHeaderCreate($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid soap-represents block action[action:'.$data['action'].']');
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