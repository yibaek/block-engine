<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver\Mssql;

use Exception;
use Throwable;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CProcedureParameterMode;

/**
 * Query/Procedure executor for Microsoft SQLServer
 *
 * @since SRT-140
 */
class MssqlHandler
{
    private $connection;

    /**
     * @param MssqlMgr $connection
     */
    public function __construct(MssqlMgr $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $procedure
     * @param array $params
     * @param array $attributes Attributes for {@link PDO::prepare()}
     * @return array|null
     * @throws Exception
     */
    public function executeProcedure(string $procedure, array $params, array $attributes = []): ?array
    {
        try {
            $parameterMgr = $this->setParameterManager($params);

            return $this->connection->executeProcedure(
                $this->makeProcedureString($procedure, $parameterMgr),
                $parameterMgr,
                $attributes
            );
        } catch (MssqlStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->connection->getLogger()->exception($ex, $procedure);
            throw new MssqlStorageException('Failed to execute procedure');
        }
    }

    /**
     * @param string $query
     * @param array $params
     * @param array $attributes Attributes for {@link PDO::prepare()}
     * @return array|int
     * @throws Exception
     */
    public function executeQuery(string $query, array $params, array $attributes = [])
    {
        try {
            // set parameter manager
            $parameterMgr = $this->setParameterManager($params, true);

            // execute query
            return $this->connection->executeQuery($query, $parameterMgr, $attributes);
        } catch (MssqlStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->connection->getLogger()->exception($ex, $query);
            throw new MssqlStorageException('Failed to execute query');
        }
    }

    /**
     * @param array $params
     * @param bool $isQuery
     * @return ParameterManager
     */
    private function setParameterManager(array $params, bool $isQuery = false): ParameterManager
    {
        $parameterMgr = new ParameterManager();

        if ($isQuery === true) {
            foreach ($params as $param) {
                $parameterMgr->addParameter(new Parameter($param[0], null, $param[2], $param[3], null, null, $param[1]));
            }
            return $parameterMgr;
        }

        foreach ($params as $param) {
            switch ($param[1]) {
                case CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_OUT:
                    $parameter = new Parameter($param[0], $param[1], null, $param[2], $param[3], (int)$param[4]);
                    break;
                case CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_INOUT:
                    $parameter = new Parameter($param[0], $param[1], $param[2], $param[3], $param[4], (int)$param[5]);
                    break;
                default:
                    $parameter = new Parameter($param[0], $param[1], $param[2], $param[3], null);
                    break;
            }

            $parameterMgr->addParameter($parameter);
        }

        return $parameterMgr;
    }

    /**
     * @param string $procedure
     * @param ParameterManager $parameterMgr
     * @return string
     */
    private function makeProcedureString(string $procedure, ParameterManager $parameterMgr): string
    {
        return $procedure.'('.$parameterMgr->makeQueryString().')';
    }
}