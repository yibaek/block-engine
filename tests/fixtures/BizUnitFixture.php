<?php declare(strict_types=1);

namespace Tests\fixtures;

use Ntuple\Synctree\Plan\PlanManager;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Plan\Stack\StackManager;
use Ntuple\Synctree\Plan\Unit\TransactionManager;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandler;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\BizunitCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Operator\Context\ResponseContextCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Operator\RequestOperator;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Operator\ResponseOperator;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\EndPoint\Url;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\HashMap\CHashMapCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\Parameter\CParameterCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CParameterType;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CProtocolMethod;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive\CBoolean;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive\CInteger;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive\CString;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Property\OperatorProperty;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Variable\VariableGet;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Template\FunctionHandle\Feature\Block;
use Ntuple\Synctree\Template\FunctionHandle\FunctionHandler;
use Ntuple\Synctree\Template\Operator\Operator;
use Ntuple\Synctree\Template\Operator\OperatorAggregator;
use Ntuple\Synctree\Template\Operator\Unit\OperatorUnitAggregator;
use Ntuple\Synctree\Template\Operator\Unit\SelfUnit;
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Tests\engine\Log\LogMessageMock;

/**
 * 기본적인 bizunit 실행을 위한 블럭 구조 구현
 *
 * @since SYN-672
 */
class BizUnitFixture
{
    private $responseDataVarName;
    private $planStorage;
    private $planManager;
    private $txManager;

    public function __construct($responseDataVarName = 'responseData')
    {
        if (!defined('PLAN_VERSION')) {
            define('PLAN_VERSION', '3.1.0');
        }

        $this->responseDataVarName = $responseDataVarName;

        $plan = (new PlanStorage())->setOrigin(CommonUtil::getHeaders([]), !empty($params) ? $params : []);
        $planManager = new PlanManager($plan);
        $planManager->setPlanVersion(PLAN_VERSION);
        $plan->setLogger(new LogMessageMock());
        $plan->setStackManager(new StackManager());

        $this->planStorage = $plan;
        $this->planManager = $planManager;

        $this->txManager = new TransactionManager();
        $this->planStorage->setTransactionManager($this->txManager);
    }

    public function getResponseDataContainerName(): string
    {
        return $this->responseDataVarName;
    }

    public function getPlanStorage(): PlanStorage
    {
        return $this->planStorage;
    }

    public function getPlanManager(): PlanManager
    {
        return $this->planManager;
    }

    public function getTransactionManager(): TransactionManager
    {
        return $this->txManager;
    }

    public function initializeTransactionManager(array $bizUnitInfo = null): void
    {
        $bizUnitInfo = $bizUnitInfo ?? [
                'plan-id' => md5(date('U.u')),
                'bizunit-version' => '1.0',
                'revision-id' => md5(date('U.u')),
                'plan-environment' => 'dev',
            ];

        $this->getTransactionManager()->setBizunit($bizUnitInfo);
    }


    public function getBizunitWithBasicTemplate(
        PlanStorage $plan,
        ExtraManager $extra,
        IBlock ...$blocks): OperatorAggregator
    {

        $statements = new BlockAggregator(...$blocks);

        return $this->getOperatorAggregator($plan, $extra, $statements);
    }

    /**
     * @param PlanStorage $plan
     * @param ExtraManager $extra
     * @return RequestOperator
     */
    public function getRequestOperator(PlanStorage $plan, ExtraManager $extra): RequestOperator
    {
        return new RequestOperator($plan, 'request', $extra,
            new OperatorProperty($plan, $extra, new CString($plan, $extra, 'from')),
            new OperatorProperty($plan, $extra, new CString($plan, $extra, 'to')),
            new CProtocolMethod($plan, $extra, CProtocolMethod::PROTOCOL_METHOD_POST),
            new Url($plan, $extra, new CString($plan, $extra, 'https://gen.synctree.com')),
            new CHashMapCreate($plan, $extra, new BlockAggregator()),
            new CHashMapCreate($plan, $extra, new BlockAggregator())
        );
    }

    /**
     * @param PlanStorage $planStorage
     * @param ExtraManager $extra
     * @return ResponseOperator
     */
    public function getResponseOperator(PlanStorage $planStorage, ExtraManager $extra): ResponseOperator
    {
        return new ResponseOperator($planStorage, 'response', $extra,
            new OperatorProperty($planStorage, $extra, new CString($planStorage, $extra, 'from')),
            new OperatorProperty($planStorage, $extra, new CString($planStorage, $extra, 'to')),
            $this->getResponseContextCreate($planStorage, $extra)
        );
    }

    /**
     * @param PlanStorage $plan
     * @param ExtraManager $extra
     * @return ResponseContextCreate
     */
    public function getResponseContextCreate(PlanStorage $plan, ExtraManager $extra): ResponseContextCreate
    {
        return new ResponseContextCreate(
            $plan, $extra,
            new CInteger($plan, null, 200),
            new CHashMapCreate($plan, $extra, new BlockAggregator(
                new CParameterCreate(
                    $plan, $extra,
                    new CString($plan, $extra, 'Content-Type'),
                    new CString($plan, $extra, 'application/json'),
                    new CString($plan, $extra, 'Content Type'),
                    new CParameterType($plan, $extra, CParameterType::PARAMETER_TYPE_STRING),
                    new CBoolean($plan, $extra, false)
                )
            )),
            new CHashMapCreate($plan, $extra, new BlockAggregator(
                new CParameterCreate(
                    $plan, $extra,
                    new CString($plan, $extra, $this->getResponseDataContainerName()),
                    new VariableGet($plan, $extra, new CString($plan, $extra, $this->getResponseDataContainerName())),
                    new CString($plan, $extra, $this->getResponseDataContainerName()),
                    new CParameterType($plan, $extra, CParameterType::PARAMETER_TYPE_STRING),
                    new CBoolean($plan, $extra, false)
                )
            ))
        );
    }

    /**
     * @param PlanStorage $plan
     * @param ExtraManager $extra
     * @param BlockAggregator $statements
     * @return OperatorAggregator
     */
    public function getOperatorAggregator(
        PlanStorage $plan,
        ExtraManager $extra,
        BlockAggregator $statements): OperatorAggregator
    {
        return new OperatorAggregator(
            new Operator($plan, Operator::OPERATOR_ACTION_BASIC,
                new OperatorUnitAggregator(
                    new SelfUnit($plan, 'gen-bizunit', SelfUnit::STATUS_TYPE_BASIC,
                        new FunctionHandler($plan, new Block($plan,
                            new BlockHandler($plan,
                                new BlockAggregator(
                                    new BizunitCreate($plan, $extra,
                                        $this->getRequestOperator($plan, $extra),
                                        $statements,
                                        $this->getResponseOperator($plan, $extra)
                                    )
                                )
                            )))
                    )
                )
            )
        );
    }
}