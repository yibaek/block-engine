<?php
namespace models\rdb;

interface IRdbProxyShardDbConnInfo
{
    public function getHost(): string;
    public function getPort(): string;
    public function getUsername(): ?string;
}