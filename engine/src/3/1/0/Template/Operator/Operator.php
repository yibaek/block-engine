<?php
namespace Ntuple\Synctree\Template\Operator;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\Operator\Unit\OperatorUnitAggregator;
use Ntuple\Synctree\Template\Operator\Unit\SelfUnit;

class Operator
{
    public const OPERATOR_TYPE = 'operator';
    public const OPERATOR_ACTION_BASIC = 'basic';
    public const OPERATOR_ACTION_SECURE = 'secure';

    private $type;
    private $action;
    private $units;
    private $storage;

    /**
     * Operator constructor.
     * @param PlanStorage $storage
     * @param string|null $action
     * @param OperatorUnitAggregator|null $units
     */
    public function __construct(PlanStorage $storage, string $action = null, OperatorUnitAggregator $units = null)
    {
        $this->storage = $storage;
        $this->type = self::OPERATOR_TYPE;
        $this->action = $action;
        $this->units = $units;
    }

    /**
     * @param array $data
     * @return Operator
     * @throws Exception
     */
    public function setData(array $data): Operator
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->units = $this->setUnits($data['template']['lists']);

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
            'template' => [
                'lists' => $this->getTemplateEachUnit()
            ]
        ];
    }

    /**
     * @throws Exception
     */
    public function do(): void
    {
        foreach ($this->units as $unit) {
            $unit->do();
        }
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param array $datas
     * @return OperatorUnitAggregator
     * @throws Exception
     */
    private function setUnits(array $datas): OperatorUnitAggregator
    {
        $unitAggregate = new OperatorUnitAggregator();
        foreach ($datas as $data) {
            switch ($data['type']) {
                case SelfUnit::OPERATOR_UNIT_TYPE:
                    $unitAggregate->addOperatorUnit((new SelfUnit($this->storage))->setData($data));
                    break;

                default:
                    throw new \RuntimeException('invalid operator unit type[type:'.$data['type'].']');
            }
        }

        return $unitAggregate;
    }

    /**
     * @return array
     */
    private function getTemplateEachUnit(): array
    {
        $resData = [];
        foreach ($this->units as $unit) {
            $resData[] = $unit->getTemplate();
        }

        return $resData;
    }
}