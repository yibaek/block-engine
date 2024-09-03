<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Logic\Expression;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CLogicalOperator;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class CExpressionCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'expression';
    public const ACTION = 'create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $condition;
    private $logicalOperator;
    private $expressionResult;

    /**
     * CExpressionCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $logicalOperator
     * @param IBlock|null $condition
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $logicalOperator = null, IBlock $condition = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->condition = $condition;
        $this->logicalOperator = $logicalOperator;
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
        $this->condition = $this->setBlock($this->storage, $data['template']['condition']);
        $this->logicalOperator = $this->setBlock($this->storage, $data['template']['logical-operator']);

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
                'condition' => $this->condition->getTemplate(),
                'logical-operator' => $this->logicalOperator->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): array
    {
        try {
            // first expression
            if ($this->expressionResult === null) {
                return [false, $this->condition->do($blockStorage)];
            }

            return $this->checkContinueExpression($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Logic-Expression'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param bool $result
     */
    public function setExpressionResult(bool $result = null): void
    {
        $this->expressionResult = $result;
    }

    /**
     * @param array $blockStorage
     * @return array
     */
    private function checkContinueExpression(array &$blockStorage): array
    {
        $operator = $this->logicalOperator->do($blockStorage);

        if ($operator === CLogicalOperator::LOGICAL_TYPE_OR && true === $this->expressionResult) {
            return  [true, ''];
        }

        if ($operator === CLogicalOperator::LOGICAL_TYPE_AND && false === $this->expressionResult) {
            return  [true, ''];
        }

        $condition = $this->condition->do($blockStorage);
        return [false, $operator.$condition];
    }
}