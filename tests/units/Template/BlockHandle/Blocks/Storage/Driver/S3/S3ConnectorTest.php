<?php declare(strict_types=1);
namespace Tests\units\Template\BlockHandle\Blocks\Storage\Driver\S3;

use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Models\Rdb\IRDbMgr;
use Ntuple\Synctree\Models\Rdb\IRDbHandler;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3Connector;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use PHPUnit\Framework\TestCase;

/**
 * @since SRT-187
 */
class S3ConnectorTest extends TestCase
{
    const DUMMY_ID = 1;

    /** @var string[] studio 쪽에서 설정되는 연결 정보 */
    private static $connInfo = [
        'storage_type' => 'awss3',
        'storage_version' => '2006-03-01',
        'storage_region' => 'ap-northeast-2',
        'storage_bucket' => 'test-bucket',
        'storage_key' => 'test-key',
        'storage_secret' => 'test-secret',
    ];

    /**
     * @test
     * @testdox 블럭은 S3 연결 정보를 연관배열 형태로 반환한다.
     * @throws ISynctreeException
     */
    public function block_returns_connection_info_as_array()
    {
        // arrange
        $storage = $this->createPlanStorageMock(self::$connInfo);
        $extra = $this->createStub(ExtraManager::class);

        $connector = $this->createStub(IBlock::class);
        $connector->method('do')->willReturn(self::DUMMY_ID);

        $sut = new S3Connector($storage, $extra, $connector, 'dummy');

        $blockStorage = [];
        $result = $sut->do($blockStorage);

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('region', $result[0]);
        $this->assertArrayHasKey('version', $result[0]);
        $this->assertArrayHasKey('credentials', $result[0]);
        $this->assertEquals(self::$connInfo['storage_key'], $result[0]['credentials']['key']);
        $this->assertEquals(self::$connInfo['storage_secret'], $result[0]['credentials']['secret']);
        $this->assertIsString($result[1]); //bucket name
    }

    private function createPlanStorageMock(array $connInfo = [])
    {
        $connectionInfo = [
            'storage_db_info' => json_encode($connInfo)
        ];

        $mock = $this->createStub(PlanStorage::class);

        $rdbManager = $this->createStub(IRdbMgr::class);
        $rdbHandler = $this->createStub(IRDbHandler::class);

        $rdbManager->method('getHandler')->willReturn($rdbHandler);
        $rdbHandler->method('executeGetStorageDetail')->willReturn($connectionInfo);

        $mock->method('getRdbStudioResource')->willReturn($rdbManager);

        return $mock;
    }


    /**
     * @test
     * @testdox 블럭은 지정된 유형의 연결 정보가 아닐 경우 예외를 발생시킨다.
     * @throws ISynctreeException
     */
    public function block_fails_if_storage_type_mismatches()
    {
        $connInfo = self::$connInfo;
        $connInfo['storage_type'] = 'random-text';

        // arrange
        $storage = $this->createPlanStorageMock($connInfo);
        $extra = $this->createStub(ExtraManager::class);

        $connector = $this->createStub(IBlock::class);
        $connector->method('do')->willReturn(self::DUMMY_ID);

        $sut = new S3Connector($storage, $extra, $connector, 'dummy');

        // assert
        $this->expectException(InvalidArgumentException::class);

        $blockStorage = [];
        $sut->do($blockStorage);
    }

}