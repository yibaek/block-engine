<?php
namespace Ntuple\Synctree\Template\FunctionHandle\Feature;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandler;
use Ntuple\Synctree\Template\FunctionHandle\IFunctionFeature;
use Ntuple\Synctree\Template\Storage\IOperatorStorage;
use Ntuple\Synctree\Template\Storage\ResponseOperator;
use Throwable;

class Block implements IFunctionFeature
{
    public const FEATURE_TYPE = 'block';

    private $type;
    private $blocks;
    private $storage;

    public function __construct(PlanStorage $storage, BlockHandler $blocks = null)
    {
        $this->storage = $storage;
        $this->type = self::FEATURE_TYPE;
        $this->blocks = $blocks;
    }

    /**
     * @param array $datas
     * @return IFunctionFeature
     * @throws Exception
     */
    public function setDatas(array $datas): IFunctionFeature
    {
        $this->type = $datas['type'];
        $this->blocks = $this->setBlocks($datas['template']['blocks']);

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'template' => [
                'blocks' => $this->blocks->getTemplate()
            ]
        ];
    }

    /**
     * @return IOperatorStorage
     * @throws Throwable
     */
    public function do(): IOperatorStorage
    {
        $response = $this->blocks->do();
        return new ResponseOperator($response['header'], $response['body'], $response['status_code']);
    }

    /**
     * @param array $blocks
     * @return BlockHandler
     * @throws Exception
     */
    private function setBlocks(array $blocks): BlockHandler
    {
        return (new BlockHandler($this->storage))->setData($blocks);
    }
}