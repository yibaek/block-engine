<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Helper\Document;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class DocumentManager implements IBlock
{
    public const TYPE = 'document';

    private $storage;
    private $block;

    /**
     * DocumentManager constructor.
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
            case Comment::ACTION:
                $this->block = (new Comment($this->storage))->setData($data);
                return $this;

            case NoteComment::ACTION:
                $this->block = (new NoteComment($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid document block action[action:'.$data['action'].']');
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