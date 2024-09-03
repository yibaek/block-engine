<?php declare(strict_types=1);

namespace Tests\engine\Models\Rdb;

use Exception;
use Ntuple\Synctree\Models\Rdb\IRDbHandler;
use Tests\libraries\NotImplementedException;


/**
 * RDB 데이터 획득을 위한 테스트 대역
 *
 * @since SYN-672
 */
class RdbHandlerMock implements IRDbHandler
{
    private $storageType;
    private $storageInfo;

    private $dictMock = [];
    private $dictReadCounter = [];

    public function __construct(?array $storageInfo = null, ?string $storageType = null)
    {
        $this->storageType = $storageType ?? 'test';
        $this->storageInfo = $storageInfo ?? [
            'storage_host' => 'dummy.host.local',
            'storage_port' => '3333',
            'storage_dbname' => 'dummy_db',
            'storage_charset' => 'utf-8',
            'storage_username' => 'dummy_user',
            'storage_password' => 'dummy_password',
        ];
    }

    public function getStorageInfoMock(): array
    {
        return $this->storageInfo;
    }

    public function getStorageType(): string
    {
        return $this->storageType;
    }


    public function executeGetStorageDetail(int $storageDetailID, string $key): array
    {
        return [
            'storage_db_info' => json_encode($this->getStorageInfoMock()),
            'storage_type' => $this->getStorageType()
        ];
    }

    /**
     * @throws Exception
     */
    public function executeAddMetricsHistory(string $metricsID, int $value, string $label = null, int $bizunitSno = null, int $revisionSno = null): bool
    {
        throw new NotImplementedException();
    }

    /**
     * Dummy data
     *
     * @param int $sno query ID
     * @param string $environment dev|stage|production|..
     * @return string Query text
     */
    public function executeGetQueryBySNO(int $sno, string $environment): string
    {
        return 'select now();';
    }

    /**
     * @throws Exception
     */
    public function executeGetBatchList(): array
    {
        throw new NotImplementedException();
    }

    /**
     * @throws Exception
     */
    public function executeGetBatchBizunitInfo(int $bizunitSno, int $revisionSno): array
    {
        throw new NotImplementedException();
    }

    /**
     * @throws Exception
     */
    public function executeAddBatchHistory(string $processId, int $batchID, int $batchMatchID, int $bizunitSno, int $revisionSno, string $batchMode, int $retryCnt, int $execCnt = 0): int
    {
        throw new NotImplementedException();
    }

    /**
     * @throws Exception
     */
    public function executeUpdateBatchHistory(int $batchHistoryID, string $batchSuccess, string $batchMessage): bool
    {
        throw new NotImplementedException();
    }

    /**
     * @throws Exception
     */
    public function executeGetBatchInfo(int $batchMatchID): array
    {
        throw new NotImplementedException();
    }

    /**
     * @throws Exception
     */
    public function executeGetDictionaryDetail(int $dictionaryDetailID, string $environment): array
    {
        $this->increaseDictionaryReadCounter($dictionaryDetailID);

        return ['key_value' => $this->dictMock[$dictionaryDetailID]];
    }

    /**
     * Dictionary 값 설정을 모사한다.
     * 읽기 카운터가 없었다면 0으로 초기화한다.
     *
     * @param int $id
     * @param string $keyValue
     * @return void
     */
    public function setDictionaryStub(int $id, string $keyValue): void
    {
        $this->dictMock[$id] = $keyValue;
        if (!array_key_exists($id, $this->dictReadCounter)) {
            $this->dictReadCounter[$id] = 0;
        }
    }

    /**
     * @param int $id dictionary ID
     * @return int dictionary stub 읽기 접근 횟수
     */
    public function getDictionaryReadCounter(int $id): int
    {
        return $this->dictReadCounter[$id] ?? 0;
    }

    /**
     * Dictionary mock 읽기 카운터를 증가
     *
     * @param int $dictionaryDetailID
     */
    private function increaseDictionaryReadCounter(int $dictionaryDetailID): void
    {
        if (array_key_exists($dictionaryDetailID, $this->dictMock)) {
            $this->dictReadCounter[$dictionaryDetailID]++;
        } else {
            $this->dictReadCounter[$dictionaryDetailID] = 1;
        }
    }
}