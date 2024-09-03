<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String;

use Exception;
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
use Ntuple\Synctree\Util\ValidationUtil;
use Throwable;

class CStringConcat implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'string-concat';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $target;
    private $values;

    /**
     * CStringConcat constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $target
     * @param BlockAggregator|null $values
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $target = null, BlockAggregator $values = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->target = $target;
        $this->values = $values;
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
        $this->target = $this->setBlock($this->storage, $data['template']['target']);
        $this->values = $this->setBlocks($this->storage, $data['template']['values']);

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
                'target' => $this->target->getTemplate(),
                'values' => $this->getTemplateEachValue()
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
            $target = $this->getTarget($blockStorage);

            foreach ($this->values as $value) {
                $target .= $this->getValue($blockStorage, $value);
            }

            return $target;
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-String-Concat'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachValue(): array
    {
        $resData = [];
        foreach ($this->values as $value) {
            $resData[] = $value->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getTarget(array &$blockStorage): string
    {
        try {
            return ValidationUtil::isConvertStringType($this->target->do($blockStorage));
        } catch (\InvalidArgumentException $ex) {
            throw (new InvalidArgumentException('Util-String-Concat: Invalid target: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @param $value
     * @return mixed
     * @throws ISynctreeException
     */
    private function getValue(array &$blockStorage, $value)
    {
        try {
            return ValidationUtil::isConvertStringType($value->do($blockStorage));
        } catch (\InvalidArgumentException $ex) {
            throw (new InvalidArgumentException('Util-String-Concat: Invalid value: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }
}