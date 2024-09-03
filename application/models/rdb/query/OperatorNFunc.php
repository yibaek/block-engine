<?php
namespace models\rdb\query;

abstract class OperatorNFunc
{
    /**
     * OperatorNFunc constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param string $column
     * @param string $value
     * @return string
     */
    public function in(string $column, string $value): string
    {
        return $column.' IN ('.$value.')';
    }
}