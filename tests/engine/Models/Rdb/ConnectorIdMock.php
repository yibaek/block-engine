<?php declare(strict_types=1);

namespace Tests\engine\Models\Rdb;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Tests\libraries\NotImplementedException;

/**
 * Storage query mapping ID block
 *
 * @since SYN-672
 */
class ConnectorIdMock implements IBlock
{
    public const TYPE = 'storage';
    public const ACTION = 'statement-manager-query-id';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $querySno;
    private $queryID;

    public function __construct(PlanStorage $storage, ?ExtraManager $extra=null)
    {
        $this->storage = $storage;
        $this->extra = $extra;
    }

    /**
     * @throws NotImplementedException
     */
    public function setData(array $data): IBlock
    {
        throw new NotImplementedException();
    }

    public function getTemplate(): array
    {
        return [
            'type' => self::TYPE,
            'action' => self::ACTION,
            'extra' => $this->extra->getData(),
            'template' => [
                'query-sno' => $this->querySno->getTemplate(),
                'query-id' => $this->queryID->getTemplate()
            ]
        ];
    }

    public function do(array &$blockStorage): int
    {
        return 0;
    }
}