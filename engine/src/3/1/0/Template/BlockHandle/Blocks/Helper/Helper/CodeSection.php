<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Helper\Helper;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CodeSection implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'helper';
    public const ACTION = 'code-section';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $statements;

    /**
     * CodeSection constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param BlockAggregator|null $statements
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, BlockAggregator $statements = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->statements = $statements;
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
        $this->statements = $this->setBlocks($this->storage, $data['template']['statements']);

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
                'statements' => $this->getTemplateEachStatement()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     */
    public function do(array &$blockStorage): void
    {
        // do satements
        foreach ($this->statements as $statement) {
            $statement->do($blockStorage);
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachStatement(): array
    {
        $resData = [];
        foreach ($this->statements as $statement) {
            $resData[] = $statement->getTemplate();
        }

        return $resData;
    }
}