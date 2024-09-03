<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CArithmeticOperator implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'arithmetic-operator';

    public const ARITHMETIC_TYPE_ADDITION = '+';
    public const ARITHMETIC_TYPE_SUBTRACTION = '-';
    public const ARITHMETIC_TYPE_MULTIPLICATION = '*';
    public const ARITHMETIC_TYPE_DIVISION = '/';
    public const ARITHMETIC_TYPE_MODULUS = '%';
    public const ARITHMETIC_TYPE_INCREMENT = '++';
    public const ARITHMETIC_TYPE_DECREMENT = '--';
//    public const ARITHMETIC_TYPE_ADDITION_ASSIGNMENT = '+=';
//    public const ARITHMETIC_TYPE_SUBTRACTION_ASSIGNMENT = '-+';
//    public const ARITHMETIC_TYPE_MULTIPLICATION_ASSIGNMENT = '*=';
//    public const ARITHMETIC_TYPE_DIVISION_ASSIGNMENT = '/=';
//    public const ARITHMETIC_TYPE_MODULUS_ASSIGNMENT = '%=';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $operator;

    /**
     * CArithmeticOperator constructor.
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