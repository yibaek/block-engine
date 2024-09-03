<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Math;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CArithmeticOperator;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class CMathCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'math';
    public const ACTION = 'create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $leftValue;
    private $rightValue;
    private $arithmeticOperator;

    /**
     * CMathCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $leftValue
     * @param IBlock|null $arithmeticOperator
     * @param IBlock|null $rightValue
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $leftValue = null, IBlock $arithmeticOperator = null, IBlock $rightValue = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->leftValue = $leftValue;
        $this->rightValue = $rightValue;
        $this->arithmeticOperator = $arithmeticOperator;
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
        $this->leftValue = $this->setBlock($this->storage, $data['template']['left-value']);
        $this->rightValue = $this->setBlock($this->storage, $data['template']['right-value']);
        $this->arithmeticOperator = $this->setBlock($this->storage, $data['template']['arithmetic-operator']);

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
                'left-value' => $this->leftValue->getTemplate(),
                'right-value' => $this->rightValue->getTemplate(),
                'arithmetic-operator' => $this->arithmeticOperator->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return float|int
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage)
    {
        try {
            return $this->calculate($this->getLeftValue($blockStorage), $this->getOperator($blockStorage), $this->getRightValue($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-Math'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return float|int
     * @throws ISynctreeException
     */
    private function getLeftValue(array &$blockStorage)
    {
        $leftValue = $this->leftValue->do($blockStorage);
        if (!is_int($leftValue) && !is_float($leftValue)) {
            throw (new InvalidArgumentException('Util-Math: Invalid left value: Not a float or integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $leftValue;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getOperator(array &$blockStorage): string
    {
        $operator = $this->arithmeticOperator->do($blockStorage);
        if (!is_string($operator)) {
            throw (new InvalidArgumentException('Util-Math: Invalid operator: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $operator;
    }

    /**
     * @param array $blockStorage
     * @return float|int|null
     * @throws ISynctreeException
     */
    private function getRightValue(array &$blockStorage)
    {
        $rightValue = $this->rightValue->do($blockStorage);
        if (!is_null($rightValue)) {
            if (!is_int($rightValue) && !is_float($rightValue)) {
                throw (new InvalidArgumentException('Util-Math: Invalid right value: Not a float or integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $rightValue;
    }

    /**
     * @param int|float $leftValue
     * @param string $operator
     * @param int|float|null $rightValue
     * @return int|float
     * @throws ISynctreeException
     */
    private function calculate($leftValue, string $operator, $rightValue = null)
    {
        switch ($operator) {
            case CArithmeticOperator::ARITHMETIC_TYPE_ADDITION:
                if (is_null($rightValue)) {
                    throw (new InvalidArgumentException('Util-Math: Invalid right value: Value is null'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
                }
                return $leftValue + $rightValue;

            case CArithmeticOperator::ARITHMETIC_TYPE_SUBTRACTION:
                if (is_null($rightValue)) {
                    throw (new InvalidArgumentException('Util-Math: Invalid right value: Value is null'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
                }
                return $leftValue - $rightValue;

            case CArithmeticOperator::ARITHMETIC_TYPE_MULTIPLICATION:
                if (is_null($rightValue)) {
                    throw (new InvalidArgumentException('Util-Math: Invalid right value: Value is null'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
                }
                return $leftValue * $rightValue;

            case CArithmeticOperator::ARITHMETIC_TYPE_DIVISION:
                if (is_null($rightValue)) {
                    throw (new InvalidArgumentException('Util-Math: Invalid right value: Value is null'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
                }
                return $leftValue / $rightValue;

            case CArithmeticOperator::ARITHMETIC_TYPE_MODULUS:
                if (is_null($rightValue)) {
                    throw (new InvalidArgumentException('Util-Math: Invalid right value: Value is null'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
                }
                return $leftValue % $rightValue;

            case CArithmeticOperator::ARITHMETIC_TYPE_INCREMENT:
                return ++$leftValue;

            case CArithmeticOperator::ARITHMETIC_TYPE_DECREMENT:
                return --$leftValue;

            default:
                throw new \RuntimeException('invalid arithmetic operator[operator:'.$operator.']');
        }
    }
}