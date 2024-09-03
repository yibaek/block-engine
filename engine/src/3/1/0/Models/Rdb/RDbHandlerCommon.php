<?php
namespace Ntuple\Synctree\Models\Rdb;

use RuntimeException;
use Throwable;

abstract class RDbHandlerCommon implements IRDbHandler
{
    private $rdb;

    /**
     * RDbHandlerCommon constructor.
     * @param IRdbMgr $rdb
     */
    public function __construct(IRdbMgr $rdb)
    {
        $this->rdb = $rdb;
    }

    /**
     * @param int $storageDetailID
     * @param string $key
     * @return array
     * @throws Throwable
     */
    public function executeGetStorageDetail(int $storageDetailID, string $key): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('sd.storage_detail_id as storage_detail_id')
                ->select('storage_id')
                ->select('storage_detail_name')
                ->select('storage_detail_description')
                ->select('storage_type')
                ->select('storage_version')
                ->selectAESDecrypt('storage_db_info', $key, 'storage_db_info')
                ->select('sd.register_date')
                ->select('sd.modify_date')
                ->select('is_del')
                ->select('del_date')
                ->select('ssl_use')
                ->select('storage_ssl_ca_path')
                ->select('storage_ssl_cert_path')
                ->select('storage_ssl_key_path')
                ->table('storage_detail sd')
                ->join('LEFT JOIN', 'storage_ssl tls', 'sd.storage_detail_id = tls.storage_detail_id')
//                ->indexhint('FORCE INDEX FOR JOIN', ['PRIMARY'])
                ->where('sd.storage_detail_id', $storageDetailID)
                ->whereAnd('is_del', 'N'));

            if (empty($resData)) {
                throw new RuntimeException('failed to get storage info[storage_detail_id:'.$storageDetailID.']');
            }

            return $resData[0];
        } catch (Throwable $ex) {
            $this->rdb->getLogger()->exception($ex);
            throw  $ex;
        }
    }

    /**
     * @param string $metricsID
     * @param int $value
     * @param string|null $label
     * @param int|null $bizunitSno
     * @param int|null $revisionSno
     * @return bool
     * @throws Throwable
     */
    public function executeAddMetricsHistory(string $metricsID, int $value, string $label = null, int $bizunitSno = null, int $revisionSno = null): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $metricsData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->selectAll()
                ->table('metrics')
                ->where('metrics_id', $metricsID));

            if (count($metricsData) < 1) {
                throw new RuntimeException('failed to get metrics[metricsID:' . $metricsID . ']');
            }

            foreach ($metricsData as $data) {
                $affectedRows = $this->rdb->executeQuery($this->rdb->getInsert()
                    ->insert('metrics_id', $metricsID)
                    ->insert('metrics_channel', $data['metrics_channel'])
                    ->insert('metrics_menu', $data['metrics_menu'])
                    ->insert('metrics_action', $data['metrics_action'])
                    ->insert('metrics_value', $value)
                    ->insert('metrics_label', $label)
                    ->insert('bizunit_sno', $bizunitSno)
                    ->insert('revision_sno', $revisionSno)
                    ->insertDatetime('register_date', date('Y-m-d H:i:s'))
                    ->table('metrics_history'));

                if ($affectedRows !== 1) {
                    throw new RuntimeException('failed to add metrics history[metricsID:'.$metricsID.', value:'.$value.']');
                }
            }

            return true;
        } catch (Throwable $ex) {
            $this->rdb->getLogger()->exception($ex);
            throw  $ex;
        }
    }

    /**
     * @param int $sno
     * @param string $environment
     * @return string
     * @throws Throwable
     */
    public function executeGetQueryBySNO(int $sno, string $environment): string
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('query_content')
                ->table('query_manager_detail')
                ->where('query_sno', $sno)
                ->whereAnd('query_environment', $environment));

            if (empty($resData)) {
                throw new RuntimeException('failed to get query content[sno:'.$sno.', environment:'.$environment.']');
            }

            return $resData[0]['query_content'];
        } catch (Throwable $ex) {
            $this->rdb->getLogger()->exception($ex);
            throw  $ex;
        }
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function executeGetBatchList(): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $batchList = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('b.batch_content')
                ->select('m.batch_match_id')
                ->select('m.bizunit_sno')
                ->select('m.revision_sno')
                ->table('batch_match m')
                ->join('INNER JOIN', 'batch b', 'm.batch_id = b.batch_id')
                ->orderBy('m.batch_match_id'));

            $resData = [];
            foreach ($batchList as $data) {
                $resData[] = [
                    'batch_match_id' => $data['batch_match_id'],
                    'batch_content' => $data['batch_content'],
                    'connect_bizunit_sno' => $data['bizunit_sno'],
                    'connect_revision_sno' => $data['revision_sno'],
                    'batch_prevent_overlapping' => true
                ];
            }

            return $resData;
        } catch (Throwable $ex) {
            $this->rdb->getLogger()->exception($ex);
            throw  $ex;
        }
    }

    /**
     * @param int $bizunitSno
     * @param int $revisionSno
     * @return array
     * @throws Throwable
     */
    public function executeGetBatchBizunitInfo(int $bizunitSno, int $revisionSno): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            // get bizunit info
            $bizunitInfo = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('bizunit_id')
                ->select('bizunit_version')
                ->table('bizunit')
                ->where('bizunit_sno', $bizunitSno));
            if (empty($bizunitInfo)) {
                throw new RuntimeException('failed to get bizunit info[bizunit sno:'.$bizunitSno);
            }

            // get revision info
            $revisionInfo = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('revision_id')
                ->select('revision_environment')
                ->table('revision')
                ->where('revision_sno', $revisionSno));
            if (empty($revisionInfo)) {
                throw new RuntimeException('failed to get revision info[revision sno:'.$revisionSno);
            }

            return array_merge($bizunitInfo[0], $revisionInfo[0]);
        } catch (Throwable $ex) {
            $this->rdb->getLogger()->exception($ex);
            throw  $ex;
        }
    }

    /**
     * @param int $batchMatchID
     * @return array
     * @throws Throwable
     */
    public function executeGetBatchInfo(int $batchMatchID): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            // get batch match info
            $batchMatchInfo = $this->rdb->executeQuery($this->rdb->getSelect()
                ->selectAll()
                ->table('batch_match')
                ->where('batch_match_id', $batchMatchID));

            if (empty($batchMatchInfo)) {
                throw new RuntimeException('failed to get batch match info[batchMatchID:'.$batchMatchID);
            }

            return $batchMatchInfo[0];
        } catch (Throwable $ex) {
            $this->rdb->getLogger()->exception($ex);
            throw  $ex;
        }
    }

    /**
     * @param string $processId
     * @param int $batchID
     * @param int $batchMatchID
     * @param int $bizunitSno
     * @param int $revisionSno
     * @param string $batchMode
     * @param int $retryCnt
     * @param int $execCnt
     * @return int
     * @throws Throwable
     */
    public function executeAddBatchHistory(string $processId, int $batchID, int $batchMatchID, int $bizunitSno, int $revisionSno, string $batchMode, int $retryCnt, int $execCnt = 0): int
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            // add batch history
            $affectedRows = $this->rdb->executeQuery($this->rdb->getInsert()
                ->insert('batch_process_id', $processId)
                ->insert('batch_id', $batchID)
                ->insert('batch_match_id', $batchMatchID)
                ->insert('redo_count', $retryCnt)
                ->insert('exec_count', $execCnt)
                ->insert('bizunit_sno', $bizunitSno)
                ->insert('revision_sno', $revisionSno)
                ->insert('batch_mode', $batchMode)
                ->insertDatetime('register_date', date('Y-m-d H:i:s'))
                ->insert('modify_date')
                ->table('batch_history'));
            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to add batch history[batchMatchID:'.$batchMatchID.']');
            }

            return $this->rdb->getLastInsertID('batch_history_id');
        } catch (Throwable $ex) {
            $this->rdb->getLogger()->exception($ex);
            throw $ex;
        }
    }

    /**
     * @param int $batchHistoryID
     * @param string $batchSuccess
     * @param string $batchMessage
     * @return bool
     * @throws Throwable
     */
    public function executeUpdateBatchHistory(int $batchHistoryID, string $batchSuccess, string $batchMessage): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            // update batch history
            $affectedRows = $this->rdb->executeQuery($this->rdb->getUpdate()
                ->update('batch_success', $batchSuccess)
                ->update('batch_message', $batchMessage)
                ->updateDatetime('modify_date', date('Y-m-d H:i:s'))
                ->table('batch_history')
                ->where('batch_history_id', $batchHistoryID));

            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to update batch history[batchHistoryID:'.$batchHistoryID.']');
            }

            return true;
        } catch (Throwable $ex) {
            $this->rdb->getLogger()->exception($ex);
            throw $ex;
        }
    }

    /**
     * @param int $dictionaryDetailID
     * @param string $environment
     * @return array
     * @throws Throwable
     */
    public function executeGetDictionaryDetail(int $dictionaryDetailID, string $environment): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('JSON_UNQUOTE(JSON_EXTRACT(key_value, CONCAT(\'$."\',CONCAT(\''.$environment.'\',\'_value\'),\'"\'))) AS key_value')
                ->select('key_name')
                ->table('dictionary_detail')
                ->where('dictionary_detail_id', $dictionaryDetailID)
                ->whereAnd('is_del', 'N'));

            if (empty($resData)) {
                throw new RuntimeException('failed to get dictionary detail info[dictionary_detail_id:'.$dictionaryDetailID.']');
            }

            return $resData[0];
        } catch (Throwable $ex) {
            $this->rdb->getLogger()->exception($ex);
            throw  $ex;
        }
    }
}
