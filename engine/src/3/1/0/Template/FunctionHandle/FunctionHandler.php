<?php
namespace Ntuple\Synctree\Template\FunctionHandle;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\FunctionHandle\Feature\Block;
use Ntuple\Synctree\Template\Storage\IOperatorStorage;

class FunctionHandler
{
    public const HANDLE_TYPE = 'function-handle';

    private $type;
    private $feature;
    private $storage;

    /**
     * FunctionHandler constructor.
     * @param PlanStorage $storage
     * @param IFunctionFeature|null $feature
     */
    public function __construct(PlanStorage $storage, IFunctionFeature $feature = null)
    {
        $this->storage = $storage;
        $this->type = self::HANDLE_TYPE;
        $this->feature = $feature;
    }

    /**
     * @param array $data
     * @return FunctionHandler
     * @throws Exception
     */
    public function setData(array $data): FunctionHandler
    {
        if (!empty($data)) {
            $this->type = $data['type'];
            $this->feature = $this->setFeature($data['template']);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'template' => $this->feature->getTemplate()
        ];
    }

    /**
     * @return IOperatorStorage
     */
    public function do(): IOperatorStorage
    {
        return $this->feature->do();
    }

    /**
     * @param array $template
     * @return IFunctionFeature
     * @throws Exception
     */
    private function setFeature(array $template): IFunctionFeature
    {
        switch ($template['type']) {
            case Block::FEATURE_TYPE:
                return (new Block($this->storage))->setDatas($template);

            default:
                throw new \RuntimeException('invalid function-handler feature type[type:'.$template['type'].']');
        }
    }
}