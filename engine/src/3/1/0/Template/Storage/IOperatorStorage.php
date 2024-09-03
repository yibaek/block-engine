<?php
namespace Ntuple\Synctree\Template\Storage;

interface IOperatorStorage
{
    public function getData(): array;
    public function setHeader(array $data, bool $isAdd = false): IOperatorStorage;
    public function setBody(array $data, bool $isAdd = false): IOperatorStorage;
    public function setStatusCode(int $code): IOperatorStorage;
    public function getHeaders(): array;
    public function getBodys();
    public function getStatusCode(): int;
}