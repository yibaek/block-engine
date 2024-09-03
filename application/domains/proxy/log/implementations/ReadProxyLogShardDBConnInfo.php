<?php
namespace domains\proxy\log\implementations;

use Throwable;
use Exception;

use libraries\util\RedisUtil;
use libraries\constant\CommonConst;
use models\redis\RedisMgr;
use models\rdb\IRdbMgr;
use models\rdb\RdbProxyShardDbConnInfo;
use models\rdb\IRdbProxyShardDbConnInfo;
use domains\proxy\log\repositories\IReadProxyLogShardDBConnInfo;

/**
 * Proxy Log 의 대상 데이터베이스 접속 정보를 proxy_id (+slave_id) 기반으로 확인
 *
 * @see https://redmine.nntuple.com/issues/6176 `slave_id`를 추가로 사용하게 된 이유
 */
class ReadProxyLogShardDBConnInfo implements IReadProxyLogShardDBConnInfo
{
    /** @var RedisMgr $redisMgr */
    protected $redisMgr;

    /** @var IRdbMgr $portalDB 프록시 로그 샤딩을 관리하는 DB */
    protected $portalDB;

    /** @var IRdbMgr $studioDB 프록시 원본데이터 bizunit_proxy가 있는 DB */
    protected $studioDB;

    public function __construct(RedisMgr $redisMgr, IRdbMgr $portalDB, IRdbMgr $studioDB)
    {
        $this->redisMgr = $redisMgr;
        $this->portalDB = $portalDB;
        $this->studioDB = $studioDB;
    }

    /**
     * 주어진 proxy id 관련 로그를 샤딩할 DB 정보를 얻는다.
     *
     * @param integer $proxy_id
     * @return IRdbProxyShardDbConnInfo
     * @throws Throwable
     */
    public function read(int $proxy_id): IRdbProxyShardDbConnInfo
    {
        $connectionData = $this->getDatafromRedis($proxy_id);

        if (empty($connectionData)) {
            $connectionData = $this->getDataFromDB($proxy_id);
            $this->setDataToRedis($proxy_id, $connectionData);
        }

        return $this->getConnectionFromData($connectionData);
    }

    /**
     * Redis에 캐싱해둔 커넥션 정보가 있으면 가져온다.
     *
     * @param integer $proxy_id
     * @return array|null 응답된 배열 안에는 host, port 키 필수
     * @throws Throwable
     */
    protected function getDatafromRedis(int $proxy_id): ?array
    {
        $return = RedisUtil::getData(
            $this->redisMgr,
            $this->getProxyLogShardRedisKey($proxy_id),
            CommonConst::REDIS_SESSION
        );
        if (!is_array($return)) {
            return null;
        }
        if (!isset($return['host'], $return['port'])) {
            return null;
        }
        return $return;
    }

    /**
     * DB에서 커넥션 정보를 가져온다.
     *
     * @param integer $proxy_id
     * @return array 응답된 배열 안에는 host, port 키 필수, username은 있을 수도 없을 수도 있음
     * @throws Throwable
     */
    protected function getDataFromDB(int $proxy_id): array
    {
        $user_id = $this->getProxyUserID($proxy_id);

        $this->portalDB->makeConnection();

        // return existing match if exists
        $connectionInfo = $this->portalDB->executeQuery(
            $this->portalDB->getSelect()
                ->select("sh.connection_string")
                ->table('proxy_shard_match m')
                ->join('INNER JOIN', 'proxy_shard_info sh', 'sh.proxy_shard_info_id = m.proxy_shard_info_id')
                ->where('m.bizunit_proxy_id', $proxy_id)
                ->whereAnd('m.user_id', $user_id)
        );

        // if no existing match then start making one
        if (!isset($connectionInfo[0]['connection_string'])) {
            $shardings = $this->portalDB->executeQuery(
                $this->portalDB->getSelect()
                    ->select('proxy_shard_info_id')
                    ->select('accum_value')
                    ->table('proxy_shard_info')
                    ->orderBy('accum_value')
                    ->limit(1)
            );
            if (count($shardings) < 1) {
                throw new Exception('no sharding info defined, please provide');
            }

            // determine values to create and update
            $shard = $shardings[0];
            $shardInfoID = $shard['proxy_shard_info_id'];
            $shardLastAccum = $shard['accum_value'];

            // create a match
            $this->portalDB->executeQuery(
                $this->portalDB->getInsert()
                    ->insert('proxy_shard_info_id', $shardInfoID)
                    ->insert('bizunit_proxy_id', $proxy_id)
                    ->insert('user_id', $user_id)
                    ->insertDatetime('register_date', date('Y-m-d H:i:s'))
                    ->table('proxy_shard_match')
            );
            $shardMatchID = $this->portalDB->getLastInsertID('proxy_shard_match_id');
            if (!is_int($shardMatchID)) {
                throw new Exception('sharding failed on insert !!!');
            }

            // update the shard
            $updated = $this->portalDB->executeQuery(
                $this->portalDB->getUpdate()
                    ->update('accum_value', $shardLastAccum + 1)
                    ->table('proxy_shard_info')
                    ->where('proxy_shard_info_id', $shardInfoID)
            );
            if ($updated !== 1) {
                throw new Exception('sharding failed on update !!!');
            }

            // get connection info
            $connectionInfo = $this->portalDB->executeQuery(
                $this->portalDB->getSelect()
                    ->select("sh.connection_string")
                    ->table('proxy_shard_info sh')
                    ->join('INNER JOIN', 'proxy_shard_match m', 'm.proxy_shard_info_id = sh.proxy_shard_info_id')
                    ->where('m.proxy_shard_match_id', $shardMatchID)
            );
        }

        // convert into data array to return
        $connectionData = [];
        $shardInfo = explode(',', $connectionInfo[0]['connection_string']);
        foreach (['host', 'port', 'username'] as $index => $configKey) {
            if (isset($shardInfo[$index]) && (string) $shardInfo[$index] !== '') {
                $connectionData[$configKey] = $shardInfo[$index];
            }
        }
        if (!isset($connectionData['host'], $connectionData['port'])) {
            throw new Exception('wrong sharding info, please correct');
        }
        return $connectionData;
    }

    /**
     * proxy_id 기준으로 bizunit_proxy 테이블 뒤져서 user_id 얻어낸다.
     * @param integer $proxy_id
     * @return integer
     */
    protected function getProxyUserID(int $proxy_id): int
    {
        $this->studioDB->makeConnection();
        $proxy = $this->studioDB->executeQuery(
            $this->studioDB->getSelect()
                ->select('user_id')
                ->table('bizunit_proxy')
                ->where('bizunit_proxy_id', $proxy_id)
        );
        if (empty($proxy) || empty($proxy[0]['user_id'])) {
            throw new Exception('bizunit proxy not found !!!');
        }
        return (int) $proxy[0]['user_id'];
    }

    /**
     * Redis에 커넥션 정보를 저장한다.
     *
     * @param integer $proxy_id
     * @param array $connection
     * @return void
     * @throws Throwable
     */
    protected function setDataToRedis(int $proxy_id, array $connection): void
    {
        RedisUtil::setDataWithExpire(
            $this->redisMgr,
            $this->getProxyLogShardRedisKey($proxy_id),
            CommonConst::REDIS_SESSION,
            CommonConst::REDIS_SESSION_EXPIRE_TIME_DAY_7,
            $connection
        );
    }

    /**
     * host, port 정보가 들어 있는 배열로부터 샤딩될 로그 DB 커넥션을 확보한다.
     *
     * @param array $connectionData
     * @return IRdbProxyShardDbConnInfo
     */
    protected function getConnectionFromData(array $connectionData): IRdbProxyShardDbConnInfo
    {
        return new RdbProxyShardDbConnInfo($connectionData['host'], $connectionData['port']);
    }

    /**
     * 커넥션 정보를 저장할 Redis 키를 결정한다.
     *
     * @param integer $proxy_id
     * @return string
     */
    protected function getProxyLogShardRedisKey(int $proxy_id): string
    {
        return 'logdb_shard_info_proxy_'.$proxy_id;
    }
}