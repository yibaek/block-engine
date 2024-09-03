<?php
namespace Ntuple\Synctree\Plan;

use Exception;
use Ntuple\Synctree\Template\Operator\Operator;
use Ntuple\Synctree\Template\Operator\OperatorAggregator;

class PlanManager
{
    private $plan;
    private $planStorage;

    /**
     * PlanManager constructor.
     * @param PlanStorage $planStorage
     */
    public function __construct(PlanStorage $planStorage)
    {
        $this->planStorage = $planStorage;
        $this->plan = [
            'flow' => []
        ];
    }

    /**
     * @param array $planData
     * @return PlanManager
     * @throws Exception
     */
    public function loadPlan(array $planData): PlanManager
    {
        $plan = $this->getPlan($planData);

        // set sequence
        $this->setSequence($this->getOperatorAggregator($plan));

        // set plan version
        $this->setPlanVersion($this->getPlanVersionFromPlan($plan));

        // set plan info
        $this->setPlanInfo($this->getPlanInfoFromPlan($plan));

        return $this;
    }

    /**
     * @return OperatorAggregator
     */
    public function getSequence(): OperatorAggregator
    {
        return $this->plan['flow']['sequence'];
    }

    /**
     * @return string|null
     */
    public function getPlanVersion(): ?string
    {
        return $this->plan['plan-version'];
    }

    /**
     * @return array
     */
    public function getPlanInfo(): array
    {
        return $this->plan['plan-info'] ?? [];
    }

    /**
     * @param OperatorAggregator $sequences
     * @return PlanManager
     */
    public function setSequence(OperatorAggregator $sequences): PlanManager
    {
        $this->plan['flow']['sequence'] = $sequences;
        return $this;
    }

    /**
     * @param string|null $planVersion
     * @return PlanManager
     */
    public function setPlanVersion(string $planVersion = null): PlanManager
    {
        $this->plan['plan-version'] = $planVersion;
        return $this;
    }

    /**
     * @param array $info
     * @return $this
     */
    public function setPlanInfo(array $info): PlanManager
    {
        $this->plan['plan-info'] = $info;
        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getTemplate(): array
    {
        return [
            'plan' => [
                'flow' => [
                    'sequence' => $this->getTemplateEachSequence()
                ],
                'plan-version' => $this->getPlanVersion(),
                'plan-info' => $this->getPlanInfo()
            ]
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getTemplateEachSequence(): array
    {
        $template = [];
        foreach ($this->getSequence() as $sequence) {
            switch ($sequence->getType()) {
                case Operator::OPERATOR_TYPE:
                    $template[] = $sequence->getTemplate();
                    break;

                default:
                    throw new \RuntimeException('invalid sequence type[type:'.$sequence->getType().']');
            }
        }

        return $template;
    }

    /**
     * @param array $planData
     * @return array
     */
    private function getPlan(array $planData): array
    {
        return $planData['plan'];
    }

    /**
     * @param array $plan
     * @return string|null
     */
    private function getPlanVersionFromPlan(array $plan): ?string
    {
        return $plan['plan-version'];
    }

    /**
     * @param array $plan
     * @return array
     */
    private function getPlanInfoFromPlan(array $plan): array
    {
        return $plan['plan-info'] ?? [];
    }

    /**
     * @param array $plan
     * @return OperatorAggregator
     * @throws Exception
     */
    private function getOperatorAggregator(array $plan): OperatorAggregator
    {
        // set operator aggregator
        $operatorAggregator = new OperatorAggregator();
        foreach ($plan['flow']['sequence'] as $data) {
            $operatorAggregator->addOperator((new Operator($this->planStorage))->setData($data));
        }

        return $operatorAggregator;
    }
}