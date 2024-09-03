<?php
namespace domains\proxy\log\implementations;

use models\rdb\IRdbProxyShardMgr;
use domains\proxy\log\entities\ProxyLog;
use domains\proxy\log\repositories\ISaveProxyLog;
use domains\proxy\log\repositories\IReadProxyLogShardDBConnInfo;

/**
 * Proxy Log를 저장
 */
class SaveProxyLog implements ISaveProxyLog
{
    /** @var IReadProxyLogShardDBConnInfo */
    protected $readShardDbRepo;

    /** @var IRdbProxyShardMgr */
    protected $rdb;

    public function __construct(IRdbProxyShardMgr $rdb, IReadProxyLogShardDBConnInfo $readShardDbRepo)
    {
        $this->readShardDbRepo = $readShardDbRepo;
        $this->rdb = $rdb;
    }

    public function saveProxyLog(ProxyLog $log): bool
    {
        $query = $this->rdb->getInsert()
            ->insert('bizunit_proxy_id', $log->getBizunitProxyId())
            ->insert('transaction_key', $log->getTransactionKey())
            ->insert('bizunit_sno', $log->getBizunitSno())
            ->insert('bizunit_id', $log->getBizunitId())
            ->insert('bizunit_version', $log->getBizunitVersion())
            ->insert('revision_sno', $log->getRevisionSno())
            ->insert('revision_id', $log->getRevisionId())
            ->insert('revision_environment', $log->getRevisionEnvironment())
            ->insert('latency', $log->getLatency())
            ->insert('size', $log->getSize())
            ->insert('response_status', $log->getResponseStatus())
            ->insertDatetime('register_date', $log->getRegisterDate())
            ->insertDatetime('timestamp_date', $log->getTimestampDate());

        if (($appID=$log->getPortalAppID()) !== null) {
            $query->insert('portal_app_id', $appID);
        }

        $conn_info = $this->readShardDbRepo->read($log->getBizunitProxyId());
        $this->rdb->makeConnection($conn_info);
        $this->rdb->executeQuery($query->table('proxy_api_log'));

        return true;
    }
}