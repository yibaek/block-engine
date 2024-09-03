<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class CDateTimeInterval implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'datetime-interval';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $intervalType;
    private $intervalAmount;

    /**
     * CDateTimeInterval constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $intervalType
     * @param IBlock|null $intervalAmount
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $intervalType = null, IBlock $intervalAmount = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->intervalType = $intervalType;
        $this->intervalAmount = $intervalAmount;
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
        $this->intervalType = $this->setBlock($this->storage, $data['template']['interval-type']);
        $this->intervalAmount = $this->setBlock($this->storage, $data['template']['interval-amount']);

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
                'interval-type' => $this->intervalType->getTemplate(),
                'interval-amount' => $this->intervalAmount->getTemplate()
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
            return [$this->getIntervalType($blockStorage) => $this->getIntervalAmount($blockStorage)];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-DateTime-Interval'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getIntervalType(array &$blockStorage): string
    {
        $intervalType = $this->intervalType->do($blockStorage);
        if (!is_string($intervalType)) {
            throw (new InvalidArgumentException('Util-DateTime-Interval: Invalid interval type: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $intervalType;
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function getIntervalAmount(array &$blockStorage): int
    {
        $intervalAmount = $this->intervalAmount->do($blockStorage);
        if (!is_int($intervalAmount)) {
            throw (new InvalidArgumentException('Util-DateTime-Interval: Invalid interval amount: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $intervalAmount;
    }
}