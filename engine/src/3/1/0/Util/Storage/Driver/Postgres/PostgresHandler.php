<?php declare(strict_types=1);

namespace Ntuple\Synctree\Util\Storage\Driver\Postgres;

use Exception;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CProcedureParameterMode;
use Throwable;

/**
 * @since SYN-389
 */
class PostgresHandler
{
    private $connection;

    /**
     * PostgresHandler constructor.
     *
     * @param PostgresMgr $connection
     */
    public function __construct(PostgresMgr $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $procedure
     * @param array $params
     * @return array|null
     * @throws PostgresStorageException|Exception
     */
    public function executeProcedure(string $procedure, array $params): ?array
    {
        try {
            // set parameter manager
            $parameterMgr = $this->setParameterManager($params);

            // execute procedure
            return $this->connection->executeProcedure($this->makeProcedureString($procedure, $parameterMgr), $parameterMgr);
        } catch (PostgresStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->connection->getLogger()->exception($ex, $procedure);
            throw new PostgresStorageException('Failed to execute procedure');
        }
    }

    /**
     * @param string $query
     * @param array $params
     * @return array|int
     * @throws Exception
     */
    public function executeQuery(string $query, array $params)
    {
        try {
            // set parameter manager
            $parameterMgr = $this->setParameterManager($params, true);

            // execute query
            return $this->connection->executeQuery($query, $parameterMgr);
        } catch (PostgresStorageException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->connection->getLogger()->exception($ex, $query);
            throw new PostgresStorageException('Failed to execute query');
        }
    }

    /**
     * @param array $params
     * @param bool $isQuery
     * @return ParameterManager
     */
    private function setParameterManager(array $params, bool $isQuery = false): ParameterManager
    {
        // set parameter manager
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

            // add parameter
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
        return $procedure.'('.$parameterMgr->makeQueryString().');';
    }
}