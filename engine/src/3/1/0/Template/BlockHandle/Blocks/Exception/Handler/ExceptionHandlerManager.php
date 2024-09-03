<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Exception\Handler;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Throwable;

class ExceptionHandlerManager implements IBlock
{
    public const TYPE = 'exception-handler';

    private $storage;
    private $block;

    /**
     * ExceptionHandlerManager constructor.
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
            case TryCatchCreate::ACTION:
                $this->block = (new TryCatchCreate($this->storage))->setData($data);
                return $this;

            case ExceptionCatcher::ACTION:
                $this->block = (new ExceptionCatcher($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid exception handler block action[action:'.$data['action'].']');
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

    /**
     * @param Throwable $ex
     */
    public function setException(Throwable $ex): void
    {
        $this->block->setException($ex);
    }
}