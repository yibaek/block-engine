<?php
namespace models\rdb\oracle\query;

use models\rdb\query\Update as UpdateCommon;

class Update extends UpdateCommon
{
    /**
     * @param string $column
     * @param string $value
     * @param string|null $bindvalue
     * @return UpdateCommon
     */
    public function updateDatetime(string $column, string $value, string $bindvalue = null): UpdateCommon
    {
        return parent::updateDatetime($column, $value, 'to_timestamp(:'.$column.', \'YYYY-MM-DD HH24:MI:SS\')');
    }
}