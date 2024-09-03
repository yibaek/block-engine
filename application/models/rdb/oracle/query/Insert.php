<?php
namespace models\rdb\oracle\query;

use models\rdb\query\Insert as InsertCommon;

class Insert extends InsertCommon
{
    /**
     * @param string|null $column
     * @param string|null $value
     * @param string|null $bindvalue
     * @return InsertCommon
     */
    public function insertDatetime(string $column = null, string $value = null, string $bindvalue = null): InsertCommon
    {
        return parent::insertDatetime($column, $value, 'to_timestamp(:'.$column.', \'YYYY-MM-DD HH24:MI:SS\')');
    }
}