<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Logic\Expression;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CExpressionManager implements IBlock
{
    public const TYPE = 'expression';

    private $storage;
    private $block;

    /**
     * CExpressionManager constructor.
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
            case CExpressionCreate::ACTION:
                $this->block = (new CExpressionCreate($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid expression block action[action:'.$data['action'].']');
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
    public function do(array &$blockStorage): array
    {
        return $this->block->do($blockStorage);
    }

    /**
     * @param bool $result
     */
    public function setExpressionResult(bool $result = null): void
    {
        $this->block->setExpressionResult($result);
    }
}