<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Helper\Document;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class NoteComment implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'document';
    public const ACTION = 'comment-note';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $comment;

    /**
     * NoteComment constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $comment
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $comment = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->comment = $comment;
    }

    /**
     * @param array $data
     * @return IBlock
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->comment = $this->setBlock($this->storage, $data['template']['comment']);

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'extra' => $this->extra->getData(),
            'template' => [
                'comment' => $this->comment->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     */
    public function do(array &$blockStorage): void
    {
    }
}