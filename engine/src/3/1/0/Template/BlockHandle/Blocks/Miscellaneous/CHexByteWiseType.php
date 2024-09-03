<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CHexByteWiseType implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'hex-bytewise-type';

    public const HEX_BYTE_WISE_TYEP_HIGH = 'H';
    public const HEX_BYTE_WISE_TYEP_LOW = 'h';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $byteWiseType;

    /**
     * CHexByteWiseType constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param string|null $type
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, string $type = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->byteWiseType = $type;
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
        $this->byteWiseType = $data['template']['type'];

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
                'type' => $this->byteWiseType
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     */
    public function do(array &$blockStorage): string
    {
        return $this->byteWiseType;
    }
}