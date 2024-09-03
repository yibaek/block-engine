<?php
namespace Ntuple\Synctree\Util\AccessControl;

interface IStatus
{
    public function getExceptionData(): array;
    public function getRateLimitHeader(): array;
    public function getData(): array;
}