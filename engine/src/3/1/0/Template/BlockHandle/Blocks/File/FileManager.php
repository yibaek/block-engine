<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\File;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\File\Adapter\Local;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class FileManager implements IBlock
{
    public const TYPE = 'file';

    private $storage;
    private $block;

    /**
     * FileManager constructor.
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
            case FilePointer::ACTION:
                $this->block = (new FilePointer($this->storage))->setData($data);
                return $this;

            case FilePointerClose::ACTION:
                $this->block = (new FilePointerClose($this->storage))->setData($data);
                return $this;

            case ReadAll::ACTION:
                $this->block = (new ReadAll($this->storage))->setData($data);
                return $this;

            case ReadLine::ACTION:
                $this->block = (new ReadLine($this->storage))->setData($data);
                return $this;

            case Local::ACTION:
                $this->block = (new Local($this->storage))->setData($data);
                return $this;

            case Write::ACTION:
                $this->block = (new Write($this->storage))->setData($data);
                return $this;

            case SeekLine::ACTION:
                $this->block = (new SeekLine($this->storage))->setData($data);
                return $this;

            case EndofFile::ACTION:
                $this->block = (new EndofFile($this->storage))->setData($data);
                return $this;

            case SetFlags::ACTION:
                $this->block = (new SetFlags($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid file block action[action:'.$data['action'].']');
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