<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Redis;

use Exception;
use Ntuple\Synctree\Log\LogMessage;
use Redis;
use RedisException;
use Throwable;

class RedisMgrWithSharding extends AbstractRedisMgr
{
    protected $logger;
    protected $config;
    private $connections;

    /**
     * RedisMgrWithSharding constructor.
     * @param LogMessage $logger
     * @param array|null $config
     * @throws Throwable
     */
    public function __construct(LogMessage $logger, array $config)
    {
        // init connection pool
        $this->connections = [];

        parent::__construct($logger, $config);
    }

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
            // get index for sharding
            if ($shardIndex === null) {
                $shardIndex = $this->getShard($key) % count($this->config['host']);
            }

            // get connection
            $connection = $this->tryConnect($this->config, $shardIndex);

            // select db
            $connection->select($dbIndex);

            return $connection;
        } catch (RedisException $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException($ex->getMessage());
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
            throw new RedisStorageException('Failed to connect');
        }
    }

    protected function close(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
            $connection = null;
        }
    }

    /**
     * @param array $config
     * @param int $index
     * @return Redis
     */
    private function tryConnect(array $config, int $index): Redis
    {
        if (false === array_key_exists($index, $this->connections) || empty($this->connections[$index])) {
            $redis = new Redis();
            $redis->connect($config['host'][$index], $config['port'][$index], $config['connection_timeout']);

            // with authentication
            if (!empty($config['auth'])) {
                $redis->auth($config['auth'][$index]);
            }

            // set connection pool
            $this->connections[$index] = $redis;
        }

        return $this->connections[$index];
    }
}
