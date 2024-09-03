<?php declare(strict_types=1);
namespace Tests\units\Util\Storage\Driver\Mssql;

use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Util\Storage\Driver\Mssql\MssqlConnectionInfo;
use Ntuple\Synctree\Util\Storage\Driver\Mssql\MssqlMgr;
use PHPUnit\Framework\TestCase;

/**
 * @since SRT-140
 */
class MssqlMgrTest extends TestCase
{
    /**
     * @test
     * @testdox 커넥션 매니저는 연결 정보로부터 유효한 DSN 문자열을 만든다.
     */
    public function manager_makes_valid_DSN_from_connection_info()
    {
        $logger = $this->createMock(LogMessage::class);
        $connInfo = new MssqlConnectionInfo([
            'host' => bin2hex(openssl_random_pseudo_bytes(10)).'local',
            'port' => 1234,
            'username' => bin2hex(openssl_random_pseudo_bytes(10)),
            'password' => bin2hex(openssl_random_pseudo_bytes(10)),
        ]);

        $sut = new MssqlMgr($logger, []);

        $dsn = $sut->makeConnectionString($connInfo);

        $this->assertStringStartsWith(MssqlConnectionInfo::DRIVER_NAME, $dsn);
        $this->assertStringContainsString('Server', $dsn);
        $this->assertStringContainsString("{$connInfo->getHost()},{$connInfo->getPort()}", $dsn);
    }

}
