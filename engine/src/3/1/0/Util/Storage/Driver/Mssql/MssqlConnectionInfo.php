<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver\Mssql;

use Exception;
use Ntuple\Synctree\Util\Storage\Driver\RdbConnectionInfo;

/**
 * @since SRT-140
 */
class MssqlConnectionInfo extends RdbConnectionInfo
{
    public const DRIVER_NAME = 'sqlsrv';
    public const DEFAULT_PORT = 1433;

    /**
     * 'charset' => {@link MssqlCharsetOption}
     * 
     * @param array $config host, port, username, password, dbname, timezone, charset
     */
    public function __construct(array $config)
    {
        try {
            parent::__construct(
                self::DRIVER_NAME,
                $config['host'] ?? 'localhost',
                (int)($config['port'] ?? self::DEFAULT_PORT),
                $config['username'] ?? 'postgres',
                $config['password'] ?? '',
                $config['dbname'] ?? '',
                $config['timezone'] ?? date_default_timezone_get(),
                $config['charset'] ?? MssqlCharsetOption::UTF8,
                $config['options'] ?? [],
                $config['ssl'] ?? null
            );
        } catch (Exception $exception) {
            throw new MssqlStorageException('Invalid connection info', 0, $exception);
        }
    }

}