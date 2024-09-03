<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Query;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class QueryCreateEx implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'statement-query-create-ex';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $query;
    private $param;

    /**
     * QueryCreateEx constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $query
     * @param IBlock|null $param
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $query = null, IBlock $param = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->query = $query;
        $this->param = $param;
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
        $this->query = $this->setBlock($this->storage, $data['template']['query']);
        $this->param = $this->setBlock($this->storage, $data['template']['param']);

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
                'query' => $this->query->getTemplate(),
                'param' => $this->param->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     * @throws Throwable
     */
    public function do(array &$blockStorage): array
    {
        try {
            return [
                $this->getQuery($blockStorage),
                $this->getParam($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Query-Create-Ex'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getQuery(array &$blockStorage): string
    {
        $query = $this->query->do($blockStorage);
        if (!is_string($query)) {
            throw (new InvalidArgumentException('Storage-Query-Create-Ex: Invalid query type: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $query;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getParam(array &$blockStorage): array
    {
        $param = $this->param->do($blockStorage);
        if (!is_null($param)) {
            if (!is_array($param)) {
                throw (new InvalidArgumentException('Storage-Query-Create-Ex: Invalid param type: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $param ?? [];
    }
}