<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Loop\Control;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\LoopBreakException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CBreak implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'loop-control';
    public const ACTION = 'break';

    private $storage;
    private $type;
    private $action;
    private $extra;

    /**
     * CBreak constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
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
            'extra' => $this->extra->getData()
        ];
    }

    /**
     * @param array $blockStorage
     * @return void
     * @throws LoopBreakException
     */
    public function do(array &$blockStorage): void
    {
        throw new LoopBreakException();
    }
}