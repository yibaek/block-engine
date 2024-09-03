<?php declare(strict_types=1);
namespace Tests\functional;

use Exception;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CProcedureParameterMode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CProcedureParameterType;
use Ntuple\Synctree\Util\Storage\Driver\Mssql\MssqlHandler;
use Ntuple\Synctree\Util\Storage\Driver\Mssql\MssqlMgr;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @since SRT-140
 */
class MssqlHandlerTest extends TestCase
{
    private $dbPassword = '';

    protected function setUp(): void
    {
        parent::setUp();
        // PHPUnit 실행 시, 환경 변수가 _ENV가 아니라 _SERVER로 들어온다.
        $this->dbPassword = $_SERVER['MSSQL_PASSWORD'];
    }

    /**
     * @test
     * @throws Exception
     */
    public function raw_connection_works()
    {
        $conn = new PDO('sqlsrv:Server=es.ntuple,1433;Database=AdventureWorks2017', 'sa', $this->dbPassword);

        $stmt = $conn->prepare('{ CALL GetImmediateManager(?, ?) }');
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $id = 1;
        $out = null;
        $stmt->bindParam(1, $id, PDO::PARAM_INT, 0);
        $stmt->bindParam(2, $out, PDO::PARAM_INT, PDO::SQLSRV_PARAM_OUT_DEFAULT_SIZE);
        $res = $stmt->execute();

        $stmt = null;
        $this->assertEquals(99, $out);

    }

    /**
     * @test
     * @throws Exception
     */
    public function procedure_with_output_param()
    {
        $logger = $this->createMock(LogMessage::class);
        $config = $this->getConnectionConfig();

        $fieldName = 'outputValueParam';
        $params = [
            [
                1, CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_IN,
                1, CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_INTEGER, 'val'
            ],
            [
                2, CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_OUT,
                null, CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_INTEGER,
                $fieldName, PDO::SQLSRV_PARAM_OUT_DEFAULT_SIZE
            ],
        ];

        $connection = new MssqlMgr($logger, $config);
        $sut = new MssqlHandler($connection);

        $result = $sut->executeProcedure('GetImmediateManager', $params);

        $this->assertEquals(99, $result['output'][$fieldName]);
    }


    /**
     * @test
     * @throws Exception
     */
    public function procedure_returns_result()
    {
        $logger = $this->createMock(LogMessage::class);
        $config = $this->getConnectionConfig();

        $params = [
            [
                1, CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_IN,
                'Pak', CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_STRING, 'val'
            ]
        ];

        $connection = new MssqlMgr($logger, $config);
        $sut = new MssqlHandler($connection);

        $result = $sut->executeProcedure('GetEmployeeList', $params);

        $this->assertEquals('Jae', $result['result'][0][0]['FirstName']);
    }


    private function getConnectionConfig(): array
    {
        return [
            'driver' => 'sqlsrv',
            'host' => 'es.ntuple',
            'port' => 1433,
            'dbname' => 'AdventureWorks2017',
            'username' => 'sa',
            'password' => $this->dbPassword,
            'options' => [],
            'ssl' => null
        ];
    }
}
