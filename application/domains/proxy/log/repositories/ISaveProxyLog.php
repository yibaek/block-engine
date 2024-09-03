<?php
namespace domains\proxy\log\repositories;

use domains\proxy\log\entities\ProxyLog;

/**
 * Proxy Log를 저장
 */
interface ISaveProxyLog
{
    public function saveProxyLog(ProxyLog $log);
}