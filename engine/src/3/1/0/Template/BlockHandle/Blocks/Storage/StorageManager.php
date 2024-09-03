<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mssql\MssqlConnector;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mssql\MssqlCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mssql\MssqlExecuteProcedure;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mssql\MssqlExecuteQuery;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mysql\MysqlConnector;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mysql\MysqlCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mysql\MysqlExecuteProcedure;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Mysql\MysqlExecuteQuery;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Nosql\NosqlConnector;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Nosql\NosqlCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Nosql\NosqlDeleteItem;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Nosql\NosqlGetItem;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Nosql\NosqlPutItem;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Nosql\NosqlUpdateItem;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Oracle\OracleConnector;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Oracle\OracleCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Oracle\OracleExecuteProcedure;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Oracle\OracleExecuteQuery;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Postgres\PostgresConnector;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Postgres\PostgresCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Postgres\PostgresExecuteProcedure;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Postgres\PostgresExecuteQuery;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Redis\RedisConnector;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Redis\RedisCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Redis\RedisDel;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Redis\RedisExist;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Redis\RedisGet;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Redis\RedisSetEx;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters\S3ObjectParamContentEncoding;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters\S3ObjectParamContentType;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters\S3ObjectParamExpires;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters\S3ObjectParamMetaData;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters\S3ObjectParamStorageClass;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3Connector;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3Create;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3DeleteObject;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3GetObject;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3ListObjects;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3PutObject;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Manager\QueryID;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Procedure\ProcedureCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Procedure\ProcedureCreateEx;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Procedure\ProcedureInOutParameter;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Procedure\ProcedureOutParameter;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Procedure\ProcedureParam;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Procedure\ProcedureParameter;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Query\QueryCreateEx;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Query\QueryParam;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Query\QueryParameter;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Query\Transaction\Commit;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Query\Transaction\Rollback;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class StorageManager implements IBlock
{
    public const TYPE = 'storage';

    private $storage;
    private $block;

    /**
     * StorageManager constructor.
     * @param PlanStorage $storage
     * @param IBlock|null $block
     */
    public function __construct(PlanStorage $storage, IBlock $block = null)
    {
        $this->storage = $storage;
        $this->block = $block;
    }

    /**
     * @param array $data
     * @return IBlock
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        switch ($data['action']) {
            case NosqlConnector::ACTION:
                $this->block = (new NosqlConnector($this->storage))->setData($data);
                return $this;

            case NosqlCreate::ACTION:
                $this->block = (new NosqlCreate($this->storage))->setData($data);
                return $this;

            case NosqlDeleteItem::ACTION:
                $this->block = (new NosqlDeleteItem($this->storage))->setData($data);
                return $this;

            case NosqlGetItem::ACTION:
                $this->block = (new NosqlGetItem($this->storage))->setData($data);
                return $this;

            case NosqlPutItem::ACTION:
                $this->block = (new NosqlPutItem($this->storage))->setData($data);
                return $this;

            case NosqlUpdateItem::ACTION:
                $this->block = (new NosqlUpdateItem($this->storage))->setData($data);
                return $this;

            case ProcedureCreate::ACTION:
                $this->block = (new ProcedureCreate($this->storage))->setData($data);
                return $this;

            case ProcedureCreateEx::ACTION:
                $this->block = (new ProcedureCreateEx($this->storage))->setData($data);
                return $this;

            case ProcedureParam::ACTION:
                $this->block = (new ProcedureParam($this->storage))->setData($data);
                return $this;

            case ProcedureParameter::ACTION:
                $this->block = (new ProcedureParameter($this->storage))->setData($data);
                return $this;

            case ProcedureOutParameter::ACTION:
                $this->block = (new ProcedureOutParameter($this->storage))->setData($data);
                return $this;

            case ProcedureInOutParameter::ACTION:
                $this->block = (new ProcedureInOutParameter($this->storage))->setData($data);
                return $this;

            case MssqlConnector::ACTION:
                $this->block = (new MssqlConnector($this->storage))->setData($data);
                return $this;

            case MssqlCreate::ACTION:
                $this->block = (new MssqlCreate($this->storage))->setData($data);
                return $this;

            case MssqlExecuteProcedure::ACTION:
                $this->block = (new MssqlExecuteProcedure($this->storage))->setData($data);
                return $this;

            case MssqlExecuteQuery::ACTION:
                $this->block = (new MssqlExecuteQuery($this->storage))->setData($data);
                return $this;

            case MysqlConnector::ACTION:
                $this->block = (new MysqlConnector($this->storage))->setData($data);
                return $this;

            case MysqlCreate::ACTION:
                $this->block = (new MysqlCreate($this->storage))->setData($data);
                return $this;

            case MysqlExecuteProcedure::ACTION:
                $this->block = (new MysqlExecuteProcedure($this->storage))->setData($data);
                return $this;

            case MysqlExecuteQuery::ACTION:
                $this->block = (new MysqlExecuteQuery($this->storage))->setData($data);
                return $this;

            case PostgresConnector::ACTION:
                $this->block = (new postgresConnector($this->storage))->setData($data);
                return $this;

            case PostgresCreate::ACTION:
                $this->block = (new PostgresCreate($this->storage))->setData($data);
                return $this;

            case PostgresExecuteProcedure::ACTION:
                $this->block = (new PostgresExecuteProcedure($this->storage))->setData($data);
                return $this;

            case PostgresExecuteQuery::ACTION:
                $this->block = (new PostgresExecuteQuery($this->storage))->setData($data);
                return $this;

            case RedisConnector::ACTION:
                $this->block = (new RedisConnector($this->storage))->setData($data);
                return $this;

            case RedisCreate::ACTION:
                $this->block = (new RedisCreate($this->storage))->setData($data);
                return $this;

            case RedisDel::ACTION:
                $this->block = (new RedisDel($this->storage))->setData($data);
                return $this;

            case RedisExist::ACTION:
                $this->block = (new RedisExist($this->storage))->setData($data);
                return $this;

            case RedisGet::ACTION:
                $this->block = (new RedisGet($this->storage))->setData($data);
                return $this;

            case RedisSetEx::ACTION:
                $this->block = (new RedisSetEx($this->storage))->setData($data);
                return $this;

            case OracleConnector::ACTION:
                $this->block = (new OracleConnector($this->storage))->setData($data);
                return $this;

            case OracleCreate::ACTION:
                $this->block = (new OracleCreate($this->storage))->setData($data);
                return $this;

            case OracleExecuteProcedure::ACTION:
                $this->block = (new OracleExecuteProcedure($this->storage))->setData($data);
                return $this;

            case OracleExecuteQuery::ACTION:
                $this->block = (new OracleExecuteQuery($this->storage))->setData($data);
                return $this;

            case QueryCreateEx::ACTION:
                $this->block = (new QueryCreateEx($this->storage))->setData($data);
                return $this;

            case QueryParam::ACTION:
                $this->block = (new QueryParam($this->storage))->setData($data);
                return $this;

            case QueryParameter::ACTION:
                $this->block = (new QueryParameter($this->storage))->setData($data);
                return $this;

            case Commit::ACTION:
                $this->block = (new Commit($this->storage))->setData($data);
                return $this;

            case Rollback::ACTION:
                $this->block = (new Rollback($this->storage))->setData($data);
                return $this;

            case QueryID::ACTION:
                $this->block = (new QueryID($this->storage))->setData($data);
                return $this;

            case S3Connector::ACTION:
                $this->block = (new S3Connector($this->storage))->setData($data);
                return $this;

            case S3Create::ACTION:
                $this->block = (new S3Create($this->storage))->setData($data);
                return $this;

            case S3GetObject::ACTION:
                $this->block = (new S3GetObject($this->storage))->setData($data);
                return $this;

            case S3PutObject::ACTION:
                $this->block = (new S3PutObject($this->storage))->setData($data);
                return $this;

            case S3DeleteObject::ACTION:
                $this->block = (new S3DeleteObject($this->storage))->setData($data);
                return $this;

            case S3ListObjects::ACTION:
                $this->block = (new S3ListObjects($this->storage))->setData($data);
                return $this;

            case S3ObjectParamContentType::ACTION:
                $this->block = (new S3ObjectParamContentType($this->storage))->setData($data);
                return $this;

            case S3ObjectParamExpires::ACTION:
                $this->block = (new S3ObjectParamExpires($this->storage))->setData($data);
                return $this;

            case S3ObjectParamMetaData::ACTION:
                $this->block = (new S3ObjectParamMetaData($this->storage))->setData($data);
                return $this;

            case S3ObjectParamStorageClass::ACTION:
                $this->block = (new S3ObjectParamStorageClass($this->storage))->setData($data);
                return $this;

            case S3ObjectParamContentEncoding::ACTION:
                $this->block = (new S3ObjectParamContentEncoding($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid storage block action[action:'.$data['action'].']');
        }
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return $this->block->getTemplate();
    }

    /**
     * @param array $blockStorage
     * @return mixed
     */
    public function do(array &$blockStorage)
    {
        return $this->block->do($blockStorage);
    }
}