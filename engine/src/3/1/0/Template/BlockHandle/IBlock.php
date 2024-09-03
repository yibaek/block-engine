<?php
namespace Ntuple\Synctree\Template\BlockHandle;

interface IBlock
{
    public function setData(array $data): IBlock;
    public function getTemplate(): array;
    public function do(array &$blockStorage);
}