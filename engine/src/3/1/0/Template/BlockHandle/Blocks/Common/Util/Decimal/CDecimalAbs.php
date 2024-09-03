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

class CDecimalAbs implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'decimal-abs';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $decimal;

    /**
     * CDecimalAbs constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $decimal
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $decimal = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->decimal = $decimal;
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
        $this->decimal = $this->setBlock($this->storage, $data['template']['decimal']);

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
                'decimal' => $this->decimal->getTemplate()
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
            return $this->getDecimal($blockStorage)->abs();
        } catch (DecimalException $ex) {
            throw (new CommonException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-Decimal-Abs'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return DecimalSupport
     * @throws ISynctreeException
     */
    private function getDecimal(array &$blockStorage): DecimalSupport
    {
        $decimal = $this->decimal->do($blockStorage);
        if (!$decimal instanceof DecimalSupport) {
            throw (new InvalidArgumentException('Util-Decimal-Abs: Invalid decimal: Not a decimal type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $decimal;
    }
}