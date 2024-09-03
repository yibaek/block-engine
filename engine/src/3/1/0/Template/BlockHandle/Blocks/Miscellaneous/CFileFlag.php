<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CFileFlag implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'file-flag';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $flag;

    /**
     * CFileFlag constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param string|null $flag
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, string $flag = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->flag = $flag;
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
        $this->flag = $data['template']['flag'];

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
                'flag' => $this->flag
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return int
     */
    public function do(array &$blockStorage): int
    {
        return $this->flag;
    }
}