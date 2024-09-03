<?php
namespace domains\proxy\log\repositories;

use models\rdb\IRdbProxyShardDbConnInfo;

/**
 * Proxy Log 의 대상 데이터베이스 접속 정보를 proxy_id 기반으로 확인
 */
interface IReadProxyLogShardDBConnInfo
{
    public function read(int $proxy_id): IRdbProxyShardDbConnInfo;
}