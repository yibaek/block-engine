<?php
namespace Ntuple\Synctree\Models\Rdb;

interface IRDbHandler
{
    public function executeGetStorageDetail(int $storageDetailID, string $key): array;
    public function executeAddMetricsHistory(string $metricsID, int $value, string $label = null, int $bizunitSno = null, int $revisionSno = null): bool;
    public function executeGetQueryBySNO(int $sno, string $environment): string;
    public function executeGetBatchList(): array;
    public function executeGetBatchBizunitInfo(int $bizunitSno, int $revisionSno): array;
    public function executeAddBatchHistory(string $processId, int $batchID, int $batchMatchID, int $bizunitSno, int $revisionSno, string $batchMode, int $retryCnt, int $execCnt = 0): int;
    public function executeUpdateBatchHistory(int $batchHistoryID, string $batchSuccess, string $batchMessage): bool;
    public function executeGetBatchInfo(int $batchMatchID): array;
    public function executeGetDictionaryDetail(int $dictionaryDetailID, string $environment): array;
}