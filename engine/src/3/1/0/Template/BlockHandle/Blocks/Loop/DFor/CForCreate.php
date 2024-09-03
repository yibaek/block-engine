<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Loop\DFor;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\LoopBreakException;
use Ntuple\Synctree\Exceptions\Inner\LoopContinueException;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class CForCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'for';
    public const ACTION = 'create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $init;
    private $condition;
    private $counter;
    private $statements;

    /**
     * CForCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $init
     * @param IBlock|null $condition
     * @param IBlock|null $counter
     * @param BlockAggregator|null $statements
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $init = null, IBlock $condition = null, IBlock $counter = null, BlockAggregator $statements = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->init = $init;
        $this->condition = $condition;
        $this->counter = $counter;
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
        $this->init = $this->setBlock($this->storage, $data['template']['init']);
        $this->condition = $this->setBlock($this->storage, $data['template']['condition']);
        $this->counter = $this->setBlock($this->storage, $data['template']['counter']);
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
                'init' => $this->init->getTemplate(),
                'condition' => $this->condition->getTemplate(),
                'counter' => $this->counter->getTemplate(),
                'statements' => $this->getTemplateEachStatement()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return void
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): void
    {
        try {
            // push stack
            $this->storage->getStackManager()->push();

            // execute init section
            $this->executeInit($blockStorage);

            // do loop
            $this->doLoop($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Loop-For'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } finally {
            $this->storage->getStackManager()->pop();
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachStatement(): array
    {
        $resData = [];
        foreach ($this->statements as $value) {
            $resData[] = $value->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @throws ISynctreeException
     */
    private function doLoop(array &$blockStorage): void
    {
        try {
            // execute condition section
            while ($this->executeCondition($blockStorage) === true) {
                // execute statements section
                foreach ($this->statements as $statement) {
                    $statement->do($blockStorage);
                }

                // execute counter section
                $this->executeCounter($blockStorage);
            }
        } catch (LoopBreakException $ex) {
            return;
        } catch (LoopContinueException $ex) {
            // execute counter section
            $this->executeCounter($blockStorage);

            // do loop for continue
            $this->doLoop($blockStorage);
        }
    }

    /**
     * @param array $blockStorage
     * @throws ISynctreeException
     */
    private function executeInit(array &$blockStorage)
    {
        $inits = $this->getInit($blockStorage);
        if ($inits !== null) {
            foreach ($inits as $init) {
                $init->do($blockStorage);
            }
        }
    }

    /**
     * @param array $blockStorage
     * @return bool
     * @throws ISynctreeException
     */
    private function executeCondition(array &$blockStorage): bool
    {
        $conditions = $this->getCondition($blockStorage);
        if ($conditions === null) {
            return true;
        }

        $expressionResult = false;
        foreach ($conditions as $condition) {
            $expressionResult = $condition->do($blockStorage);
        }

        return $expressionResult;
    }

    /**
     * @param array $blockStorage
     * @throws ISynctreeException
     */
    private function executeCounter(array &$blockStorage)
    {
        $counters = $this->getCounter($blockStorage);
        if ($counters !== null) {
            foreach ($counters as $counter) {
                $counter->do($blockStorage);
            }
        }
    }

    /**
     * @param array $blockStorage
     * @return BlockAggregator|null
     * @throws ISynctreeException
     */
    private function getInit(array &$blockStorage): ?BlockAggregator
    {
        $init = $this->init->do($blockStorage);
        if (!is_null($init)) {
            if (!$init instanceof BlockAggregator) {
                throw (new InvalidArgumentException('Loop-For: Invalid init: Not a null or section type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $init;
    }

    /**
     * @param array $blockStorage
     * @return BlockAggregator|null
     * @throws ISynctreeException
     */
    private function getCondition(array &$blockStorage): ?BlockAggregator
    {
        $condition = $this->condition->do($blockStorage);
        if (!is_null($condition)) {
            if (!$condition instanceof BlockAggregator) {
                throw (new InvalidArgumentException('Loop-For: Invalid condition: Not a null or section type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $condition;
    }

    /**
     * @param array $blockStorage
     * @return BlockAggregator|null
     * @throws ISynctreeException
     */
    private function getCounter(array &$blockStorage): ?BlockAggregator
    {
        $counter = $this->counter->do($blockStorage);
        if (!is_null($counter)) {
            if (!$counter instanceof BlockAggregator) {
                throw (new InvalidArgumentException('Loop-For: Invalid counter: Not a null or section type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $counter;
    }
}