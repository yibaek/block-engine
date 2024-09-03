<?php
namespace models\rdb;

use models\redis\RedisMgr;
use models\rdb\mysql\MariaDbMgr;
use models\rdb\mysql\MariaDbShardMgr;
use models\rdb\mysql\MariaDbProxyShardMgr;
use models\rdb\oracle\OracleMgr;
use models\rdb\oracle\OracleShardMgr;

class RDbManager
{
    private const DRIVER_TYPE_MYSQL = 'mysql';
    private const DRIVER_TYPE_ORACLE = 'oci';

    private $driver;

    /**
     * RDbManager constructor.
     * @param string|null $driver
     */
    public function __construct(string $driver = null)
    {
        $this->driver = $driver ?? $this->getDriver();
    }

    /**
     * @param string|null $dbname
     * @return IRdbMgr
     */
    public function getRdbMgr(string $dbname = null): IRdbMgr
    {
        switch ($this->driver) {
            case self::DRIVER_TYPE_MYSQL:
                return new MariaDbMgr(null, $dbname);
            case self::DRIVER_TYPE_ORACLE:
                return new OracleMgr(null, $dbname);
            default:
                throw new \RuntimeException('invalid rdb driver[driver:'.$this->driver.']');
        }
    }

    /**
     * @param RedisMgr $redis
     * @param IRdbMgr $centerDb
     * @param string|null $dbname
     * @return IRdbMgr
     */
    public function getShardMgr(RedisMgr $redis, IRdbMgr $centerDb, string $dbname = null): IRdbMgr
    {
        switch ($this->driver) {
            case self::DRIVER_TYPE_MYSQL:
                return new MariaDbShardMgr($redis, $centerDb, null, $dbname);
            case self::DRIVER_TYPE_ORACLE:
                return new OracleShardMgr($redis, $centerDb, null, $dbname);
            default:
                throw new \RuntimeException('invalid rdb driver[driver:'.$this->driver.']');
        }
    }

    /**
     * @param string|null $dbname
     * @return IRdbProxyShardMgr
     */
    public function getProxyShardMgr(string $dbname = null): IRdbProxyShardMgr
    {
        switch ($this->driver) {
            case self::DRIVER_TYPE_MYSQL:
                return new MariaDbProxyShardMgr($dbname);
            case self::DRIVER_TYPE_ORACLE:
            default:
                throw new \RuntimeException('invalid rdb driver[driver:'.$this->driver.']');
        }
    }

    /**
     * @return string
     */
    private function getDriver(): string
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        return $config['settings']['rdb']['driver'];
    }
}