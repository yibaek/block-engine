<?php
namespace models\rdb\query;

interface IQuery
{
    public function putQuery(string $query);
    public function getQuery(): string;
    public function getType(): string;
    public function getValues();
    public function getRawQuery(): string;
    public function getCacheKeyWithRawQuery(): string;
}