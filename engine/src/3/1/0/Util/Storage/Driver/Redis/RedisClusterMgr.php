<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Redis;

use Exception;
use RedisCluster;
use RedisClusterException;
use Throwable;

class RedisClusterMgr extends AbstractRedisMgr
{
    protected $logger;
    protected $config;
    private $connection;

    /**
     * @param int $dbIndex
     * @param int|null $shardIndex
     * @param string|null $key
     * @return RedisCluster
     * @throws RedisStorageException|Exception
     */
    protected function makeConnection(int $dbIndex, int $shardIndex = null, string $key = null): RedisCluster
    {
        try {
            // get connection
            return $this->tryConnect($this->config);
        } catch (RedisClusterException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to connect');
        }
    }

    protected function close(): void
    {
        $this->connection->close();
        $this->connection = null;
    }

    /**
     * @param array $config
     * @return RedisCluster
     * @throws RedisClusterException
     */
    private function tryConnect(array $config): RedisCluster
    {
        if (empty($this->connection)) {
            $redisCluster = new RedisCluster(
                null,
                [
                    $config['host'].':'.$config['port']
                ],
                $config['connection_timeout'], $config['read_timeout'], false, $config['auth'] ?? null);

            // set connection
            $this->connection = $redisCluster;
        }

        return $this->connection;
    }
}
