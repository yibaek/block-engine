<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Stream;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Stream\Input\File\FileRead;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class StreamManager implements IBlock
{
    public const TYPE = 'stream';

    private $storage;
    private $block;

    /**
     * StreamManager constructor.
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
            case FileRead::ACTION:
                $this->block = (new FileRead($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid stream block action[action:'.$data['action'].']');
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