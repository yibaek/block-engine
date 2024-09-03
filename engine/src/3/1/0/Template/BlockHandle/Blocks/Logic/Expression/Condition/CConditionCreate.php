<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Logic\Expression\Condition;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class CConditionCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'condition';
    public const ACTION = 'create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $leftOperand;
    private $rightOperand;
    private $compareOperator;

    /**
     * CConditionCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $leftOperand
     * @param IBlock|null $compareOperator
     * @param IBlock|null $rightOperand
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $leftOperand= null, IBlock $compareOperator = null, IBlock $rightOperand = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->leftOperand = $leftOperand;
        $this->rightOperand = $rightOperand;
        $this->compareOperator = $compareOperator;
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
        $this->leftOperand = $this->setBlock($this->storage, $data['template']['left-operand']);
        $this->compareOperator = $this->setBlock($this->storage, $data['template']['compare-operator']);
        $this->rightOperand = $this->setBlock($this->storage, $data['template']['right-operand']);

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
                'left-operand' => $this->leftOperand->getTemplate(),
                'compare-operator' => $this->compareOperator->getTemplate(),
                'right-operand' => $this->rightOperand->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): string
    {
        try {
            $leftOperand = $this->leftOperand->do($blockStorage);
            return $this->getOperand($leftOperand).$this->compareOperator->do($blockStorage).$this->getOperand($this->rightOperand->do($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Logic-Condition'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param $operand
     * @return mixed
     */
    private function getOperand($operand)
    {
        switch (gettype($operand)) {
            case 'string':
                return '\''.addslashes($operand).'\'';

            case 'boolean':
                return $operand ?'true' :'false';

            case 'NULL':
                return 'NULL';
        }

        return $operand;
    }
}