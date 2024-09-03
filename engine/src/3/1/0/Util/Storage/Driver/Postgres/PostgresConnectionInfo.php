<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver\Postgres;

use Exception;
use Ntuple\Synctree\Util\Storage\Driver\RdbConnectionInfo;
use Ntuple\Synctree\Util\Storage\Driver\RdbSslOption;

/**
 * Config 배열로부터 RDB 연결을 위한 정보를 획득
 * 
 * @since SYN-389
 */
class PostgresConnectionInfo extends RdbConnectionInfo
{
    public const DEFAULT_DRIVER = 'pgsql';
    public const DEFAULT_PORT = 5432;
    public const DEFAULT_CHARSET = 'UTF8';

    public function __construct(array $config)
    {
        try {
            parent::__construct(
                self::DEFAULT_DRIVER,
                $config['host'] ?? 'localhost',
                (int)($config['port'] ?? self::DEFAULT_PORT),
                $config['username'] ?? 'postgres',
                $config['password'] ?? '',
                $config['dbname'] ?? '',
                $config['timezone'] ?? date_default_timezone_get(),
                $config['charset'] ?? self::DEFAULT_CHARSET,
                $config['options'] ?? [],
                $config['ssl'] ?? null
            );
        } catch (Exception $exception) {
            throw new PostgresStorageException('Invalid connection info', 0, $exception);
        }
    }
}