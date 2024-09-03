<?php declare(strict_types=1);
namespace Tests\units\Template\BlockHandle\Blocks\Storage\Driver\Mssql;

use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mssql\MssqlCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mssql\MssqlExecuteQuery;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Query\QueryCreateEx;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\Storage\Driver\Mssql\MssqlMgr;
use PHPUnit\Framework\TestCase;
use Tests\libraries\BlockTestTrait;

/**
 * @since SRT-140
 */
class MssqlExecuteQueryTest extends TestCase
{
    use BlockTestTrait;

    /**
     * @test
     * @testdox 블럭은 쿼리 실행 후 결과를 가져온다.
     * @throws ISynctreeException
     */
    public function block_fetches_query_result_from_db()
    {
        // arrange
        $planStorage = $this->createStub(PlanStorage::class);
        $extraManager = $this->createStub(ExtraManager::class);
        $connectorMock = $this->createMock(MssqlCreate::class);
        $connMgrMock = $this->createMock(MssqlMgr::class);
        $queryMock = $this->createMock(QueryCreateEx::class);

        $dummyData = [0, 'username', 'password'];

        $connMgrMock->method('executeQuery')->willReturn($dummyData);
        $connectorMock->method('do')->willReturn($connMgrMock);
        $queryMock->method('do')->willReturn(['query', []]);

        $sut = new MssqlExecuteQuery($planStorage, $extraManager, $connectorMock, $queryMock);

        // act
        $blockStorage = [];
        $result = $sut->do($blockStorage);

        // assert
        $this->assertIsArray($result);
        $this->assertEquals($dummyData, $result);
    }

    /**
     * @test
     * @testdox 블럭은 정상적으로 템플릿을 반환한다.
     * @return void
     */
    public function block_returns_valid_template()
    {
        // arrange
        $planStorage = $this->createStub(PlanStorage::class);
        $extraManager = $this->createStub(ExtraManager::class);
        $connectorMock = $this->createMock(MssqlCreate::class);
        $procedureMock = $this->createMock(QueryCreateEx::class);

        $sut = new MssqlExecuteQuery(
            $planStorage, $extraManager, $connectorMock, $procedureMock);

        // act
        $result = $sut->getTemplate();

        // assert
        $this->assertTemplateIsValid($result, ['connector', 'query']);
    }
}