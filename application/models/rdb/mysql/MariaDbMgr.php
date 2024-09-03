<?php
namespace models\rdb\mysql;

use models\rdb\IRDbHandler;
use models\rdb\IRdbMgr;
use PDO;

class MariaDbMgr implements IRdbMgr
{
    private $config;
    private $connection;
    private $handler;

    use MariaDbMgrTrait;

    /**
     * MariaDbMgr constructor.
     * @param array|null $config
     * @param string $dbname
     */
    public function __construct(array $config = null, string $dbname = '')
    {
        // set config
        if (empty($config)) {
            $this->config = $this->getConfig($dbname);
        } else {
            $this->config = $config;
        }

        $this->handler = new RDbHandler($this);
    }

    /**
     * @return IRDbHandler
     */
    public function getHandler(): IRDbHandler
    {
        return $this->handler;
    }

    /**
     * @param int $attribute
     * @param int $value
     * @return void
     */
    public function setAttribute(int $attribute, int $value): void
    {
        $this->connection->setAttribute($attribute, $value);
    }

    /**
     * @param int|null $slaveID
     */
    public function makeConnection(int $slaveID = null): void
    {
        // make connection
        $this->tryConnect($this->config);

        // set default attribute
        $this->setDefaultAttribute();
    }

    /**
     * @param array $config
     */
    private function tryConnect(array $config): void
    {
        if (false === $this->isConnected()) {
            // make connection
            $this->connection = new PDO(
                $this->makeConnectingString($config),
                $config['username'], $config['password'],
                $this->getTLSOptions($config)
            );
        }
    }

    /**
     * @param $config
     * @return string
     */
    private function makeConnectingString(array $config): string
    {
        return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['dbname'], $config['charset']);
    }

    private function setDefaultAttribute(): void
    {
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
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


