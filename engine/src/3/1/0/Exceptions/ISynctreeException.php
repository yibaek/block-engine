<?php
namespace Ntuple\Synctree\Exceptions;

interface ISynctreeException
{
    public function setData($data): ISynctreeException;
    public function getData(): array;
    public function getAllData(): array;
    public function setExceptionKey(string $type, string $action): ISynctreeException;
    public function getExceptionKey(): string;
    public function setExtraData(array $data): ISynctreeException;
    public function getExtraData(): array;
}