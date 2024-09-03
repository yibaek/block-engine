<?php
namespace Ntuple\Synctree\Util\File\Adapter;

interface IAdapter
{
    public function getFileName(): string;
    public function getFile(): string;
}