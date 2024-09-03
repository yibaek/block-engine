<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Loop\DFor;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class CForCondition implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'for';
    public const ACTION = 'condition';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $expressions;

    /**
     * CForCondition constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param BlockAggregator|null $expressions
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, BlockAggregator $expressions = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->expressions = $expressions;
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
        $this->expressions = $this->setBlocks($this->storage, $data['template']['expressions']);

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
                'expressions' => $this->getTemplateEachExpression()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return bool
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): bool
    {
        try {
            return $this->executeExpression($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Loop-For-Condition'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachExpression(): array
    {
        $resData = [];
        foreach ($this->expressions as $expression) {
            $resData[] = $expression->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return bool
     */
    private function executeExpression(array &$blockStorage): bool
    {
        $sentence = '';
        $expressionResult = null;
        foreach ($this->expressions as $expression) {
            $expression->setExpressionResult($expressionResult);
            [$isBreak, $syntax] = $expression->do($blockStorage);

            $sentence .= $syntax;
            if (true === $isBreak) {
                break;
            }

            $expressionResult = $this->execute($sentence);
        }

        return $expressionResult;
    }

    /**
     * @param string $expression
     * @return mixed
     */
    private function execute(string $expression)
    {
        return eval('return '.$expression. ';');
    }
}