<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CDateTimeIntervalType implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'datetime-interval-type';

    public const DATETIME_INTERVAL_TYPE_YEAR = 'DY';
    public const DATETIME_INTERVAL_TYPE_MONTH = 'DM';
    public const DATETIME_INTERVAL_TYPE_DAY = 'DD';
    public const DATETIME_INTERVAL_TYPE_HOUR = 'TH';
    public const DATETIME_INTERVAL_TYPE_MINUTE = 'TM';
    public const DATETIME_INTERVAL_TYPE_SECOND = 'TS';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $intervalType;

    /**
     * CDateTimeIntervalType constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param string|null $intervalType
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, string $intervalType = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->intervalType = $intervalType;
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
        $this->intervalType = $data['template']['interval-type'];

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
                'interval-type' => $this->intervalType
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     */
    public function do(array &$blockStorage): string
    {
        return $this->intervalType;
    }
}