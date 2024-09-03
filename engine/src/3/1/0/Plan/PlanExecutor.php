<?php
namespace Ntuple\Synctree\Plan;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Template\Operator\Operator;
use Ntuple\Synctree\Template\Operator\OperatorAggregator;
use Ntuple\Synctree\Template\Storage\IOperatorStorage;
use Throwable;

class PlanExecutor
{
    private $planManager;
    private $planStorage;

    /**
     * PlanExecutor constructor.
     * @param PlanStorage $planStorage
     * @param PlanManager $planManager
     */
    public function __construct(PlanStorage $planStorage, PlanManager $planManager)
    {
        $this->planStorage = $planStorage;
        $this->planManager = $planManager;
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function execute(): array
    {
        $returnData = null;

        try {
            $returnData = $this->executeEachSequence($this->planManager->getSequence());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->planStorage->getLogger()->exception($ex);
            throw $ex;
        }

        return [
            $returnData->getStatusCode(),
            $returnData->getHeaders(),
            $returnData->getBodys()
        ];
    }

    /**
     * @param bool $isJson
     * @return array|false|string
     * @throws Exception
     */
    public function getTemplate(bool $isJson = false)
    {
        return (true === $isJson) ? json_encode($this->planManager->getTemplate(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE, 512) :$this->planManager->getTemplate();
    }

    /**
     * @param OperatorAggregator $sequences
     * @return IOperatorStorage
     */
    private function executeEachSequence(OperatorAggregator $sequences): IOperatorStorage
    {
        foreach ($sequences as $sequence) {
            switch ($sequence->getType()) {
                case Operator::OPERATOR_TYPE:
                    $sequence->do();
                    break;

                default:
                    throw new \RuntimeException('invalid sequence type[type:'.$sequence->getType().']');
            }
        }

        return $this->planStorage->getReturnData();
    }
}