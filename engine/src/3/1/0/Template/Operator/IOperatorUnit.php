<?php
namespace Ntuple\Synctree\Template\Operator;

interface IOperatorUnit
{
    public function setData(array $data): IOperatorUnit;
    public function getTemplate(): array;
    public function do(): void;
}