<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Analytics;

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
use Ntuple\Synctree\Util\ValidationUtil;
use Throwable;

class Event implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'analytics';
    public const ACTION = 'event';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $metricsID;
    private $label;
    private $value;

    /**
     * Event constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $metricsID
     * @param IBlock|null $label
     * @param IBlock|null $value
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $metricsID = null, IBlock $label = null, IBlock $value = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->metricsID = $metricsID;
        $this->label = $label;
        $this->value = $value;
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
        $this->metricsID = $this->setBlock($this->storage, $data['template']['metrics-id']);
        $this->label = $this->setBlock($this->storage, $data['template']['label']);
        $this->value = $this->setBlock($this->storage, $data['template']['value']);

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
                'metrics-id' => $this->metricsID->getTemplate(),
                'label' => $this->label->getTemplate(),
                'value' => $this->value->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @throws ISynctreeException
     */
    public function do(array &$blockStorage): void
    {
        try {
            $this->addMetricsHistory($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Analytics-Event'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getMetricsID(array &$blockStorage): string
    {
        $metricsID = $this->metricsID->do($blockStorage);
        if (!is_string($metricsID)) {
            throw (new InvalidArgumentException('Analytics-Event: Invalid metrics-id: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $metricsID;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     */
    private function getLebel(array &$blockStorage): ?string
    {
        try {
            $label = ValidationUtil::isConvertStringType($this->label->do($blockStorage));
            return mb_substr($label, 0, 40, 'UTF-8');
        } catch (\InvalidArgumentException $ex) {
            return null;
        }
    }

    /**
     * @param array $blockStorage
     * @return int
     */
    private function getValue(array &$blockStorage): int
    {
        $value = $this->value->do($blockStorage);
        if (is_int($value)) {
            return $value;
        }

        return 0;
    }

    /**
     * @param array $blockStorage
     * @return bool
     * @throws ISynctreeException
     */
    private function addMetricsHistory(array &$blockStorage): bool
    {
        try {
            // execute query
            return $this->storage->getRdbStudioResource()->getHandler()->executeAddMetricsHistory(
                $this->getMetricsID($blockStorage),
                $this->getValue($blockStorage),
                $this->getLebel($blockStorage),
                $this->storage->getTransactionManager()->getBizunitSno(),
                $this->storage->getTransactionManager()->getRevisionSno());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            return false;
        }
    }
}