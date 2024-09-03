<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Query;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class QueryParam implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'statement-query-param';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $parameters;

    /**
     * QueryParam constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param BlockAggregator|null $parameters
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, BlockAggregator $parameters = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->parameters = $parameters;
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
        $this->parameters = $this->setBlocks($this->storage, $data['template']['parameters']);

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
                'parameters' => $this->getTemplateEachParameter()
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
            return $this->getParameters($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Query-Param'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachParameter(): array
    {
        $resData = [];
        foreach ($this->parameters as $parameter) {
            $resData[] = $parameter->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return array
     */
    private function getParameters(array &$blockStorage): array
    {
        $resData = [];
        foreach ($this->parameters as $index => $parameter) {
            $data = $parameter->do($blockStorage);
            array_unshift($data, $index+1);
            $resData[] = $data;
        }

        return $resData;
    }
}