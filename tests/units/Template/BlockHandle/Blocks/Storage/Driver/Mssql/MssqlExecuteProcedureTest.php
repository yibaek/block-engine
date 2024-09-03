<?php
namespace Tests\units\Template\BlockHandle\Blocks\Storage\Driver\Mssql;

use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mssql\MssqlCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mssql\MssqlExecuteProcedure;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Procedure\ProcedureCreateEx;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\Storage\Driver\Mssql\MssqlMgr;
use PHPUnit\Framework\TestCase;
use Tests\libraries\BlockTestTrait;

/**
 * @since SRT-140
 */
class MssqlExecuteProcedureTest extends TestCase
{
    use BlockTestTrait;

    /**
     * @test
     * @testdox 블럭은 SP 실행하고 그 결과를 가져온다.
     * @throws ISynctreeException
     */
    public function block_fetches_proc_result_from_db()
    {
        // arrange
        $planStorage = $this->createStub(PlanStorage::class);
        $extraManager = $this->createStub(ExtraManager::class);
        $connectorMock = $this->createMock(MssqlCreate::class);
        $connMgrMock = $this->createMock(MssqlMgr::class);
        $procedureMock = $this->createMock(ProcedureCreateEx::class);

        $connectorMock->method('do')->willReturn($connMgrMock);
        $procedureMock->method('do')
            ->willReturn(['{CALL [system].[procedure]}', []]);

        $dummyData = [0, 'username', 'password'];
        $connMgrMock->method('executeProcedure')->willReturn($dummyData);

        $sut = new MssqlExecuteProcedure(
            $planStorage, $extraManager, $connectorMock, $procedureMock);

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
        $procedureMock = $this->createMock(ProcedureCreateEx::class);

        $sut = new MssqlExecuteProcedure(
            $planStorage, $extraManager, $connectorMock, $procedureMock);

        // act
        $result = $sut->getTemplate();

        // assert
        $this->assertTemplateIsValid($result, ['connector', 'procedure']);
    }
}
