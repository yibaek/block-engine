<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Log;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class LogManager implements IBlock
{
    public const TYPE = 'log';

    private $storage;
    private $block;

    /**
     * LogManager constructor.
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
            case Info::ACTION:
                $this->block = (new Info($this->storage))->setData($data);
                return $this;

            case Error::ACTION:
                $this->block = (new Error($this->storage))->setData($data);
                return $this;

            case Debug::ACTION:
                $this->block = (new Debug($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid log block action[action:'.$data['action'].']');
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
     * @return void
     */
    public function do(array &$blockStorage): void
    {
        $this->block->do($blockStorage);
    }
}