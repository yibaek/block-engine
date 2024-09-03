<?php
namespace Ntuple\Synctree\Template\Operator\Unit;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\FunctionHandle\FunctionHandler;
use Ntuple\Synctree\Template\Operator\IOperatorUnit;

class SelfUnit implements IOperatorUnit
{
    public const OPERATOR_UNIT_TYPE = 'self';
    public const STATUS_TYPE_BASIC = 'basic';

    private $id;
    private $type;
    private $status;
    private $function;
    private $storage;

    /**
     * SelfUnit constructor.
     * @param PlanStorage $storage
     * @param string|null $id
     * @param string|null $status
     * @param FunctionHandler|null $function
     */
    public function __construct(PlanStorage $storage, string $id = null, string $status = null, FunctionHandler $function = null)
    {
        $this->storage = $storage;
        $this->type = self::OPERATOR_UNIT_TYPE;
        $this->id = $id;
        $this->status = $status;
        $this->function = $function;
    }

    /**
     * @param array $data
     * @return IOperatorUnit
     * @throws Exception
     */
    public function setData(array $data): IOperatorUnit
    {
        $this->type = $data['type'];
        $this->id = $data['id'];
        $this->status = $data['status'];
        $this->function = $this->setFunction($data['template']['function']);

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'type' => $this->type,
            'template' => [
                'function' => $this->function->getTemplate()
            ]
        ];
    }

    /**
     * @throws Exception
     */
    public function do(): void
    {
        $operatorStorage = $this->function->do();

        // add self operator storage
        $this->storage->setReturnData($operatorStorage);
    }

    /**
     * @param array $data
     * @return FunctionHandler
     * @throws Exception
     */
    private function setFunction(array $data): FunctionHandler
    {
        return (new FunctionHandler($this->storage))->setData($data);
    }
}