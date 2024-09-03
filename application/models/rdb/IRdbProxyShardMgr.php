<?php
namespace models\rdb;

use models\rdb\query\Delete;
use models\rdb\query\Insert;
use models\rdb\query\IQuery;
use models\rdb\query\Select;
use models\rdb\query\Update;
use models\rdb\query\OperatorNFunc;

interface IRdbProxyShardMgr
{
    public function makeConnection(IRdbProxyShardDbConnInfo $conn_info): void;
    public function executeQuery(IQuery $queryBuilder, array $params = []);
    public function close(): void;
    public function exist(IQuery $queryBuilder, array $params = []): bool;
    public function getLastInsertID(string $alias, string $sequenceID = null): int;
    public function getSelect(): Select;
    public function getDelete(): Delete;
    public function getUpdate(): Update;
    public function getInsert(): Insert;
    public function getOperatorNFunc(): OperatorNFunc;
}