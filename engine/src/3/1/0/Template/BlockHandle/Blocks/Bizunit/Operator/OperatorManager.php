<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Operator;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class OperatorManager implements IBlock
{
    public const TYPE = 'operator';

    private $storage;
    private $block;

    /**
     * OperatorManager constructor.
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
            case RequestOperator::ACTION:
                $this->block = (new RequestOperator($this->storage))->setData($data);
                return $this;

            case ResponseOperator::ACTION:
                $this->block = (new ResponseOperator($this->storage))->setData($data);
                return $this;

            case RestfulOperator::ACTION:
                $this->block = (new RestfulOperator($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid operator block action[action:'.$data['action'].']');
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