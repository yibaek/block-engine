<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Loop\DForeach;

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

class CForeachCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'foreach';
    public const ACTION = 'create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $elements;
    private $assignKeyName;
    private $assignValueName;
    private $statements;

    /**
     * CForeachCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $elements
     * @param IBlock|null $assignKeyName
     * @param IBlock|null $assignValueName
     * @param BlockAggregator|null $statements
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $elements = null, IBlock $assignKeyName = null, IBlock $assignValueName = null, BlockAggregator $statements = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->elements = $elements;
        $this->assignKeyName = $assignKeyName;
        $this->assignValueName = $assignValueName;
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
        $this->elements = $this->setBlock($this->storage, $data['template']['element']);
        $this->assignKeyName = $this->setBlock($this->storage, $data['template']['assign-key']);
        $this->assignValueName = $this->setBlock($this->storage, $data['template']['assign-value']);
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
                'element' => $this->elements->getTemplate(),
                'assign-key' => $this->assignKeyName->getTemplate(),
                'assign-value' => $this->assignValueName->getTemplate(),
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

            // do loop
            $this->doLoop($blockStorage, $this->getElements($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Loop-Foreach'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
     * @return array
     * @throws ISynctreeException
     */
    private function getElements(array &$blockStorage): array
    {
        $elements = $this->elements->do($blockStorage);
        if (!is_array($elements)) {
            throw (new InvalidArgumentException('Loop-Foreach: Invalid element: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $elements;
    }


    /**
     * @param array $blockStorage
     * @param array $elements
     * @throws ISynctreeException
     */
    private function doLoop(array &$blockStorage, array $elements): void
    {
        try {
            $this->executeForeach($blockStorage, $elements);
        } catch (LoopBreakException $ex) {
            return;
        } catch (LoopContinueException $ex) {
            $this->doLoop($blockStorage, $elements);
        }
    }

    /**
     * @param array $blockStorage
     * @param array $elements
     * @throws ISynctreeException
     */
    private function executeForeach(array &$blockStorage, array &$elements): void
    {
        $assignKeyName = $this->getAssignKeyName($blockStorage);
        $assignValueName = $this->getAssignValueName($blockStorage);

        foreach ($elements as $key => $value) {
            unset($elements[$key]);

            if (($stack=$this->storage->getStackManager()->peek()) !== null) {
                if ($assignKeyName !== null) {
                    $stack->data[$assignKeyName] = $key;
                }
                $stack->data[$assignValueName] = $value;
            } else {
                if ($assignKeyName !== null) {
                    $blockStorage[$assignKeyName] = $key;
                }
                $blockStorage[$assignValueName] = $value;
            }

            foreach ($this->statements as $statement) {
                $statement->do($blockStorage);
            }
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getAssignKeyName(array &$blockStorage): ?string
    {
        $assignKeyName = $this->assignKeyName->do($blockStorage);
        if (!is_null($assignKeyName)) {
            if (!is_string($assignKeyName)) {
                throw (new InvalidArgumentException('Loop-Foreach: Invalid key: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $assignKeyName;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getAssignValueName(array &$blockStorage): string
    {
        $assignValueName = $this->assignValueName->do($blockStorage);
        if (!is_string($assignValueName)) {
            throw (new InvalidArgumentException('Loop-Foreach: Invalid value: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $assignValueName;
    }
}