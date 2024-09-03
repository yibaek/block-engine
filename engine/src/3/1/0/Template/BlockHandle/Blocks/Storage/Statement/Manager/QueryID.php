<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Manager;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class QueryID implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'statement-manager-query-id';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $querySno;
    private $queryID;

    /**
     * QueryID constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $querySno
     * @param IBlock|null $queryID
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $querySno = null, IBlock $queryID = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->querySno = $querySno;
        $this->queryID = $queryID;
    }

    /**
     * @param array $data
     * @return IBlock
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->querySno = $this->setBlock($this->storage, $data['template']['query-sno']);
        $this->queryID = $this->setBlock($this->storage, $data['template']['query-id']);

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'extra' => $this->extra->getData(),
            'template' => [
                'query-sno' => $this->querySno->getTemplate(),
                'query-id' => $this->queryID->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     * @throws Throwable
     */
    public function do(array &$blockStorage): string
    {
        try {
            return $this->getQuery($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Manager-Query-ID'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     */
    private function getQuery(array &$blockStorage): string
    {
        return $this->storage->getRdbStudioResource()->getHandler()->executeGetQueryBySNO($this->querySno->do($blockStorage), $this->storage->getTransactionManager()->getEnvironment());
    }
}