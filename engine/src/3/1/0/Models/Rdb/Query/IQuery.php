<?php
namespace Ntuple\Synctree\Models\Rdb\Query;

interface IQuery
{
    public function putQuery(string $query);
    public function getQuery(): string;
    public function getType(): string;
    public function getValues();
}