<?php
namespace Ntuple\Synctree\Template\FunctionHandle;

use Ntuple\Synctree\Template\Storage\IOperatorStorage;

interface IFunctionFeature
{
    public function setDatas(array $data): IFunctionFeature;
    public function getTemplate(): array;
    public function do(): IOperatorStorage;
}