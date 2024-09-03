<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Redis;

use Exception;
use Redis;
use RedisException;
use Throwable;

class RedisMgr extends AbstractRedisMgr
{
    protected $logger;
    protected $config;
    private $connection;

    /**
     * @param int $dbIndex
     * @param int|null $shardIndex
     * @param string|null $key
     * @return Redis
     * @throws RedisStorageException|Exception
     */
    protected function makeConnection(int $dbIndex, int $shardIndex = null, string $key = null): Redis
    {
        try {
            // get connection
            $connection = $this->tryConnect($this->config);

            // select db
            if (!$connection->select($dbIndex)) {
                throw new RedisStorageException('DB index is out of range');
            }

            return $connection;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to connect - ' . $ex->getMessage());
        }
    }

    protected function close(): void
    {
        $this->connection->close();
        $this->connection = null;
    }

    /**
     * @param array $config
     * @return Redis
     */
    private function tryConnect(array $config): Redis
    {
        if (empty($this->connection)) {
            $redis = new Redis();
            $redis->connect($config['host'], $config['port'], $config['connection_timeout']);

            // with authentication
            if (!empty($config['auth'])) {
                $redis->auth($config['auth']);
            }

            // set connection
            $this->connection = $redis;
        }

        return $this->connection;
    }
}
