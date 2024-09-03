<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Unit;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class ProtocolUnitManager implements IBlock
{
    public const TYPE = 'protocol-unit';

    private $storage;
    private $block;

    /**
     * ProtocolUnitManager constructor.
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
            case HttpSSL::ACTION:
                $this->block = (new HttpSSL($this->storage))->setData($data);
                return $this;

            case Secure::ACTION:
                $this->block = (new Secure($this->storage))->setData($data);
                return $this;

            case Soap::ACTION:
                $this->block = (new Soap($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid protocol-unit block action[action:'.$data['action'].']');
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