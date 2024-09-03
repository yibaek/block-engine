<?php
namespace Ntuple\Synctree\Models\Rdb;

use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Models\Rdb\Query\Delete;
use Ntuple\Synctree\Models\Rdb\Query\Insert;
use Ntuple\Synctree\Models\Rdb\Query\IQuery;
use Ntuple\Synctree\Models\Rdb\Query\Select;
use Ntuple\Synctree\Models\Rdb\Query\Update;

interface IRdbMgr
{
    public function getLogger(): LogMessage;
    public function getHandler(): IRDbHandler;
    public function makeConnection(): void;
    public function close(): void;
    public function executeQuery(IQuery $queryBuilder, array $params = []);
    public function exist(IQuery $queryBuilder, array $params = []): bool;
    public function getLastInsertID(string $alias, string $sequenceID = null): int;
    public function getSelect(): Select;
    public function getDelete(): Delete;
    public function getUpdate(): Update;
    public function getInsert(): Insert;
}