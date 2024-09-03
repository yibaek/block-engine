<?php declare(strict_types=1);
namespace Tests\units\Util\Storage\Driver\Postgres;

use Ntuple\Synctree\Util\Storage\Driver\Postgres\PostgresConnectionInfo;
use Ntuple\Synctree\Util\Storage\Driver\Postgres\PostgresMgr;
use Ntuple\Synctree\Util\Storage\Driver\RdbSslMode;
use Ntuple\Synctree\Util\Storage\Driver\RdbSslOption;
use PHPUnit\Framework\TestCase;
use Tests\engine\Log\LogMessageMock;


/**
 * @since SRT-10 Postgres SSL
 */
class PostgresMgrTest extends TestCase
{
    /**
     * @test
     * @testdox 커넥션 매니저는 SSL 연결 옵션이 설정된 연결 정보를 가지고 유효한 DSN 값을 만든다.
     */
    public function manager_makes_proper_DSN_from_SSL_enabled_connection()
    {
        $config = [
            'ssl' => RdbSslOption::fromArray([
                RdbSslOption::SSL_MODE => RdbSslMode::VERIFY_CA,
                RdbSslOption::SSL_CA_CERT => uniqid() . '.crt'
            ])
        ];

        $manager = new PostgresMgr(new LogMessageMock(), $config);
        $connectionInfo = new PostgresConnectionInfo($config);

        $dsn = $manager->makeConnectionString($connectionInfo);

        $this->assertStringContainsString('sslmode='.$config['ssl']->getMode(), $dsn);
        $this->assertStringContainsString($config['ssl']->getCACertPath(), $dsn);
    }
}