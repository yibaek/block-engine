<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Logic\Choice;

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

class CIf implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'choice';
    public const ACTION = 'if';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $expressions;
    private $statements;

    /**
     * CIf constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param BlockAggregator|null $expressions
     * @param BlockAggregator|null $statements
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, BlockAggregator $expressions = null, BlockAggregator $statements = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->expressions = $expressions;
        $this->statements = $statements;
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
        $this->statements = $this->setBlocks($this->storage, $data['template']['statements']);

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
                'expressions' => $this->getTemplateEachExpression(),
                'statements' => $this->getTemplateEachStatement()
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
            // push stack
            $this->storage->getStackManager()->push();

            $result = $this->executeExpression($blockStorage);
            if (true === $result) {
                foreach ($this->statements as $statement) {
                    $statement->do($blockStorage);
                }
            }

            return $result;
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Logic-If'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } finally {
            $this->storage->getStackManager()->pop();
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
     * @return array
     */
    private function getTemplateEachStatement(): array
    {
        $resData = [];
        foreach ($this->statements as $statement) {
            $resData[] = $statement->getTemplate();
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