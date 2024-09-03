<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver\Postgres;

use Ntuple\Synctree\Util\Storage\Driver\RdbSslMode;
use Ntuple\Synctree\Util\Storage\Driver\RdbSslOption;

/**
 * TLS 옵션을 재구성하는 mapper
 *
 * @since SRT-100
 */
class PostgresSslPolicy
{
    /**
     * TLS 옵션 구성에 따라 SSL mode 설정
     *
     * @param array $storage 솔루션 DB에 기록된 연결 정보
     */
    public function mapOptions(array $storage): RdbSslOption
    {
        $enable = $storage['ssl_use'] === 'Y';
        $hasCA = !empty($storage[RdbSslOption::SSL_CA_CERT]);
        $sslMode = RdbSslMode::DISABLED;
        if ($enable) {
            if ($hasCA) {
                $sslMode = RdbSslMode::VERIFY_CA;
            } else {
                $sslMode = RdbSslMode::REQUIRED;
            }
        }

        return RdbSslOption::fromArray([
            RdbSslOption::SSL_CA_CERT  => $storage[RdbSslOption::SSL_CA_CERT],
            RdbSslOption::SSL_CLIENT_CERT => $storage[RdbSslOption::SSL_CLIENT_CERT],
            RdbSslOption::SSL_CLIENT_KEY => $storage[RdbSslOption::SSL_CLIENT_KEY],
            RdbSslOption::SSL_MODE => $sslMode
        ]);
    }
}