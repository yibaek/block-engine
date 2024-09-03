<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Operator\Context;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Context\ProtocolContext;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class ResponseContextManager implements IBlock
{
    public const TYPE = 'response-context';

    private $storage;
    private $block;

    /**
     * ResponseContextManager constructor.
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
            case ResponseContextCreate::ACTION:
                $this->block = (new ResponseContextCreate($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid response-context block action[action:'.$data['action'].']');
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
     * @return ProtocolContext
     */
    public function do(array &$blockStorage): ProtocolContext
    {
        return $this->block->do($blockStorage);
    }
}