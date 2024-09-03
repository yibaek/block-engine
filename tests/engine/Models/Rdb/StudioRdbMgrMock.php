<?php declare(strict_types=1);

namespace Tests\engine\Models\Rdb;

use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Models\Rdb\IRDbHandler;
use Ntuple\Synctree\Models\Rdb\IRdbMgr;
use Ntuple\Synctree\Models\Rdb\Query\Delete;
use Ntuple\Synctree\Models\Rdb\Query\Insert;
use Ntuple\Synctree\Models\Rdb\Query\IQuery;
use Ntuple\Synctree\Models\Rdb\Query\Select;
use Ntuple\Synctree\Models\Rdb\Query\Update;
use Tests\libraries\NotImplementedException;

/**
 * @since SYN-672
 */
class StudioRdbMgrMock implements IRdbMgr
{
    private $rdbHandler;

    public function __construct(IRDbHandler $rdbHandler)
    {
        $this->rdbHandler = $rdbHandler;
    }

    /**
     * @throws NotImplementedException
     */
    public function getLogger(): LogMessage
    {
        throw new NotImplementedException();
    }

    public function getHandler(): IRDbHandler
    {
        return $this->rdbHandler;
    }

    /**
     * @throws NotImplementedException
     */
    public function makeConnection(): void
    {
        throw new NotImplementedException();
    }

    /**
     * @throws NotImplementedException
     */
    public function close(): void
    {
        throw new NotImplementedException();
    }

    /**
     * @throws NotImplementedException
     */
    public function executeQuery(IQuery $queryBuilder, array $params = [])
    {
        throw new NotImplementedException();
    }

    /**
     * @throws NotImplementedException
     */
    public function exist(IQuery $queryBuilder, array $params = []): bool
    {
        throw new NotImplementedException();
    }

    /**
     * @throws NotImplementedException
     */
    public function getLastInsertID(string $alias, string $sequenceID = null): int
    {
        throw new NotImplementedException();
    }

    /**
     * @throws NotImplementedException
     */
    public function getSelect(): Select
    {
        throw new NotImplementedException();
    }

    /**
     * @throws NotImplementedException
     */
    public function getDelete(): Delete
    {
        throw new NotImplementedException();
    }

    /**
     * @throws NotImplementedException
     */
    public function getUpdate(): Update
    {
        throw new NotImplementedException();
    }

    /**
     * @throws NotImplementedException
     */
    public function getInsert(): Insert
    {
        throw new NotImplementedException();
    }
}