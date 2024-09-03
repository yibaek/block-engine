<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CComparisonOperator implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'comparison-operator';

    public const COMPARISON_TYPE_EQUAL = '===';
    public const COMPARISON_TYPE_NOT_EQUAL = '!==';
    public const COMPARISON_TYPE_GREATER_THAN = '>';
    public const COMPARISON_TYPE_LESS_THAN = '<';
    public const COMPARISON_TYPE_GREATER_THAN_OR_EQUAL = '>=';
    public const COMPARISON_TYPE_LESS_THAN_OR_EQUAL = '<=';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $operator;

    /**
     * CComparisonOperator constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param string|null $operator
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, string $operator = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->operator = $operator;
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
        $this->operator = $data['template']['operator'];

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
                'operator' => $this->operator
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     */
    public function do(array &$blockStorage): string
    {
        return $this->operator;
    }
}