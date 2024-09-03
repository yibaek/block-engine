<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal;

use Exception;
use Ntuple\Synctree\Exceptions\CommonException;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\Support\Decimal\DecimalException;
use Ntuple\Synctree\Util\Support\Decimal\DecimalSupport;
use Throwable;

class CDecimalDivide implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'decimal-divide';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $leftValue;
    private $rightValue;
    private $scale;

    /**
     * CDecimalDivide constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $leftValue
     * @param IBlock|null $rightValue
     * @param IBlock|null $scale
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $leftValue = null, IBlock $rightValue = null, IBlock $scale = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->leftValue = $leftValue;
        $this->rightValue = $rightValue;
        $this->scale = $scale;
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
        $this->scale = $this->setBlock($this->storage, $data['template']['scale']);

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
                'scale' => $this->scale->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return DecimalSupport
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): DecimalSupport
    {
        try {
            return $this->getLeftValue($blockStorage)->div($this->getRightValue($blockStorage), $this->getScale($blockStorage));
        } catch (DecimalException $ex) {
            throw (new CommonException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-Decimal-Divide'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return DecimalSupport
     * @throws ISynctreeException
     */
    private function getLeftValue(array &$blockStorage): DecimalSupport
    {
        $leftValue = $this->leftValue->do($blockStorage);
        if (!$leftValue instanceof DecimalSupport) {
            throw (new InvalidArgumentException('Util-Decimal-Divide: Invalid left value: Not a decimal type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $leftValue;
    }

    /**
     * @param array $blockStorage
     * @return DecimalSupport
     * @throws ISynctreeException
     */
    private function getRightValue(array &$blockStorage): DecimalSupport
    {
        $rightValue = $this->rightValue->do($blockStorage);
        if (!$rightValue instanceof DecimalSupport) {
            throw (new InvalidArgumentException('Util-Decimal-Divide: Invalid right value: Not a decimal type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $rightValue;
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getScale(array &$blockStorage): ?int
    {
        $scale = $this->scale->do($blockStorage);
        if (!is_null($scale)) {
            if (!is_int($scale)) {
                throw (new InvalidArgumentException('Util-Decimal-Divide: Invalid scale: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $scale;
    }
}