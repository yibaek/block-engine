<?php declare(strict_types=1);
namespace Tests\units\Util\Storage\Driver\Postgres;

use Ntuple\Synctree\Util\Storage\Driver\Postgres\PostgresConnectionInfo;
use Ntuple\Synctree\Util\Storage\Driver\RdbSslMode;
use Ntuple\Synctree\Util\Storage\Driver\RdbSslOption;
use Ntuple\Synctree\Util\Storage\Exception\UnsupportedOperationException;
use PHPUnit\Framework\TestCase;


/**
 * @since SRT-10 Postgres SSL
 */
class PostgresConnectionInfoTest extends TestCase
{
    const SHARED_PATH = '/random/somewhere/';

    /**
     * @test
     * @testdox 기본 형태 스토리지 객체는 빈 옵션 필드를 갖는다.
     */
    public function connection_without_ssl_has_empty_option_fields(): void
    {
        // given
        $testConfig = [
            'host' => 'localhost',
        ];

        // when
        $connectionInfo = new PostgresConnectionInfo($testConfig);

        // then
        $this->assertEmpty($connectionInfo->getOptions());
        $this->assertFalse($connectionInfo->isSSLEnabled());
    }

    /**
     * @test
     * @testdox SSL 설정이 되지 않은 연결 정보는 getSSLMode 호출 시 {@link UnsupportedOperationException} 예외를 던진다.
     */
    public function connection_without_ssl_throws_unsupported_operation_when_getting_ssl_mode(): void
    {
        // arrange
        $this->expectException(UnsupportedOperationException::class);

        $testConfig = [
            'host' => 'localhost',
        ];

        // act
        $connectionInfo = new PostgresConnectionInfo($testConfig);
        $connectionInfo->getSSLMode();

        // assert
        $this->assertFalse($connectionInfo->isSSLEnabled());
    }

    /**
     * @test
     * @testdox SSL 켜진 스토리지 객체는 옵션 필드에 필요한 인증서 경로를 갖고 있다. SSL mode 기본값은 `verify-ca`이다.
     */
    public function connection_with_ca_cert_has_required_option_fields(): void
    {
        // given
        $config = [
            'host' => 'localhost',
            'port' => 5432,
            'ssl' => RdbSslOption::fromArray([
                RdbSslOption::SSL_CA_CERT =>  self::SHARED_PATH . uniqid() . '.crt',
            ])
        ];

        // when
        $connectionInfo = new PostgresConnectionInfo($config);

        $options = $connectionInfo->getSSLOptions();

        // then
        $this->assertTrue($connectionInfo->isSSLEnabled());
        $this->assertEquals(RdbSslMode::VERIFY_CA , $connectionInfo->getSSLMode());
    }

    /**
     * @test
     * @testdox 스토리지 정보의 인증서 구성은 SSL option 객체에 정확하게 설정된다.
     * @dataProvider provideInvalidClientCert
     * @param string $mode
     * @param string[][] $composition
     * @since SRT-100
     */
    public function connection_with_ssl_config_will_be_set_properly(string $mode, array $composition): void
    {
        // given
        $ssl = RdbSslOption::fromArray($composition);
        $config = [
            'ssl' => $ssl
        ];

        // when
        $connectionInfo = new PostgresConnectionInfo($config);

        // then
        $this->assertTrue($connectionInfo->isSSLEnabled());
        $this->assertEquals($mode , $connectionInfo->getSSLMode());
    }

    /**
     * @return string[][][] 클라이언트 인증서, key 둘 중 하나가 빠진 구성
     * @since SRT-100
     */
    public function provideInvalidClientCert(): array
    {
        return [
            'no key' => [
                RdbSslMode::VERIFY_CA,
                [
                    RdbSslOption::SSL_CA_CERT => self::SHARED_PATH . uniqid() . '.crt',
                    RdbSslOption::SSL_CLIENT_CERT => self::SHARED_PATH . uniqid() .'.crt',
                ]
            ],
            'no cert' => [
                RdbSslMode::VERIFY_CA,
                [
                    RdbSslOption::SSL_CA_CERT => self::SHARED_PATH . uniqid() . '.crt',
                    RdbSslOption::SSL_CLIENT_KEY => self::SHARED_PATH . uniqid() .'.key',
                ]
            ],
            'full' => [
                RdbSslMode::VERIFY_CA,
                [
                    RdbSslOption::SSL_CA_CERT => self::SHARED_PATH . uniqid() . '.crt',
                    RdbSslOption::SSL_CLIENT_CERT => self::SHARED_PATH . uniqid() .'.crt',
                    RdbSslOption::SSL_CLIENT_KEY => self::SHARED_PATH . uniqid() .'.key',
                ]
            ]
        ];
    }
}