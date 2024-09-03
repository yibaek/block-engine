<?php
namespace Ntuple\Synctree\Models\Rdb;

use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Models\Rdb\Mysql\MariaDbMgr;

class RDbManager
{
    private const DRIVER_TYPE_MYSQL = 'mysql';

    private $logger;
    private $driver;

    /**
     * RDbManager constructor.
     * @param LogMessage $logger
     * @param string|null $driver
     */
    public function __construct(LogMessage $logger, string $driver = null)
    {
        $this->logger = $logger;
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
                return new MariaDbMgr($this->logger, null, $dbname);
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