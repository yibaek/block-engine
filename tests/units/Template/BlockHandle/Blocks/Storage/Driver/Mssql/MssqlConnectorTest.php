<?php
namespace Tests\units\Template\BlockHandle\Blocks\Storage\Driver\Mssql;

use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Models\Rdb\IRDbHandler;
use Ntuple\Synctree\Models\Rdb\IRdbMgr;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mssql\MssqlConnector;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use PHPUnit\Framework\TestCase;

/**
 * @since SRT-140
 */
class MssqlConnectorTest extends TestCase
{

    /**
     * @test
     * @testdox 블럭은 정상적으로 템플릿을 반환한다.
     */
    public function connector_returns_valid_template()
    {
        $planStorage = $this->createStub(PlanStorage::class);
        $extraManager = $this->createStub(ExtraManager::class);

        $connector = $this->createStub(IBlock::class);
        $encryptKey = 'dummy-text';

        $sut = new MssqlConnector($planStorage, $extraManager, $connector, $encryptKey);

        $result = $sut->getTemplate();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertEquals(MssqlConnector::TYPE, $result['type']);
        $this->assertEquals(MssqlConnector::ACTION, $result['action']);
        $this->assertArrayHasKey('extra', $result);
        $this->assertArrayHasKey('template', $result);
    }

    /**
     * @test
     * @testdox Connector 객체는 정상적인 connection 정보를 반환한다.
     * @throws ISynctreeException
     */
    public function connector_returns_valid_connection()
    {
        // arrange
        $planStorage = $this->createPlanStorageFixture();
        $extraManager = $this->createStub(ExtraManager::class);

        $connector = $this->createStub(IBlock::class);
        $dummyId = 1;
        $connector->method('do')->willReturn($dummyId);

        $encryptKey = 'dummy-text';

        $sut = new MssqlConnector($planStorage, $extraManager, $connector, $encryptKey);

        // act
        $blockStorage = [];
        $result = $sut->do($blockStorage);

        // assert
        $this->assertIsArray($result);
    }


    private function getDummyConnectionInfo(): array
    {
        return [
            'storage_host' => 'localhost',
            'storage_port' => 1433,
            'storage_dbname' => 'dbo',
            'storage_charset' => 'cp959',
            'storage_username' => 'sa',
            'storage_password' => 'pw',
            'storage_options' => null
        ];
    }

    private function createPlanStorageFixture(): PlanStorage
    {
        $storage = $this->createStub(PlanStorage::class);
        $rdbStudioStub = $this->createStub(IRdbMgr::class);
        $handlerStub = $this->createStub(IRDbHandler::class);

        $storageDetail = [
            'storage_type' => 'mssql',
            'storage_db_info'=> json_encode($this->getDummyConnectionInfo())
        ];

        $handlerStub->method('executeGetStorageDetail')
            ->willReturn($storageDetail);

        $rdbStudioStub->method('getHandler')
            ->willReturn($handlerStub);

        $storage->method('getRdbStudioResource')
            ->willReturn($rdbStudioStub);

        return $storage;
    }
}
