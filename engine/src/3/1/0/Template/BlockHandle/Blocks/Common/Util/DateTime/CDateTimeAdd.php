<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime;

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
use Ntuple\Synctree\Util\Support\DateTime\DateTimeSupport;
use Throwable;

class CDateTimeAdd implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'datetime-add';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $datetime;
    private $intervals;

    /**
     * CDateTimeAdd constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $datetime
     * @param BlockAggregator|null $intervals
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $datetime = null, BlockAggregator $intervals = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->datetime = $datetime;
        $this->intervals = $intervals;
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
        $this->datetime = $this->setBlock($this->storage, $data['template']['datetime']);
        $this->intervals = $this->setBlocks($this->storage, $data['template']['intervals']);

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
                'datetime' => $this->datetime->getTemplate(),
                'intervals' => $this->getTemplateEachInterval()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return DateTimeSupport
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): DateTimeSupport
    {
        try {
            return ($this->getDateTime($blockStorage))->add($this->getIntervals($blockStorage), true);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-DateTime-Add'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachInterval(): array
    {
        $resData = [];
        foreach ($this->intervals as $interval) {
            $resData[] = $interval->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return DateTimeSupport
     * @throws ISynctreeException
     */
    private function getDateTime(array &$blockStorage): DateTimeSupport
    {
        $datetime = $this->datetime->do($blockStorage);
        if (!$datetime instanceof DateTimeSupport) {
            throw (new InvalidArgumentException('Util-DateTime-Add: Invalid datetime'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $datetime;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getIntervals(array &$blockStorage): array
    {
        $resData = [];
        foreach ($this->intervals as $interval) {
            $data = $interval->do($blockStorage);
            if (!is_array($data) || empty($data)) {
                throw (new InvalidArgumentException('Util-DateTime-Add: Invalid intervals'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            $key = key($data);
            $resData[$key] = $data[$key];
        }

        return $resData;
    }
}