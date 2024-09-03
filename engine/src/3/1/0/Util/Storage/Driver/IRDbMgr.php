<?php
namespace Ntuple\Synctree\Util\Storage\Driver;

interface IRDbMgr
{
    public function close(): void;
    public function commit(): void;
    public function rollback(): void;
}