<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CNull implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'primitive';
    public const ACTION = 'null';

    private $storage;
    private $type;
    private $action;
    private $extra;

    /**
     * CNull constructor.
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
     * @return null
     */
    public function do(array &$blockStorage)
    {
        return null;
    }
}