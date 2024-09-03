<?php
namespace models\rdb\mysql;

use PDO;
use models\rdb\IRdbProxyShardMgr;
use models\rdb\IRdbProxyShardDbConnInfo;

class MariaDbProxyShardMgr implements IRdbProxyShardMgr
{
    use MariaDbMgrTrait;

    protected $connection;
    protected $config;

    /**
     * MariaDbShardMgr constructor.
     *
     * @param string $dbname
     */
    public function __construct(string $dbname = '')
    {
        // set config
        $this->config = $this->getConfig($dbname);
    }

    public function makeConnection(IRdbProxyShardDbConnInfo $conn_info): void
    {
        $this->connection = new PDO(
            $this->makeConnectingString($conn_info),
            $conn_info->getUsername() ?? $this->config['username'], $this->config['password'],
            $this->getTLSOptions($this->config)
        );

        // setDefaultAttribute
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
    }

    protected function makeConnectingString(IRdbProxyShardDbConnInfo $conn_info): string
    {
        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $conn_info->getHost(),
            $conn_info->getPort(),
            $this->config['dbname'],
            $this->config['charset']
        );
    }

    /**
     * @since SYN-731, SRT-14
     * @param array $config credentials configs
     * @return array Options parameter for PDO
     */
    private function getTLSOptions(array $config): array
    {
        $tlsEnabled = ($config['tls-enable'] ?? false) === 'true';
        return $tlsEnabled ? [
            PDO::MYSQL_ATTR_SSL_CA => $config['tls-ca-cert'] ?? '',
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => ($config['tls-verify-cert'] ?? false) === 'true'
        ] : [];
    }
}