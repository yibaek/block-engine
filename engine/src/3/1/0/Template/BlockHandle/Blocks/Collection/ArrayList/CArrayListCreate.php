<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\ArrayList;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\Parameter\CParameter;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class CArrayListCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'arraylist';
    public const ACTION = 'create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $values;

    /**
     * CArrayListCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param BlockAggregator|null $values
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, BlockAggregator $values = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->values = $values;
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
        $this->values = $this->setBlocks($this->storage, $data['template']['values']);

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
                'values' => $this->getTemplateEachValue()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): array
    {
        try {
            $items = [];
            foreach ($this->values as $index => $value) {
                $items[] = $this->addArrayList($value->do($blockStorage));
            }

            return $items;
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('ArrayList-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachValue(): array
    {
        $resData = [];
        foreach ($this->values as $value) {
            $resData[] = $value->getTemplate();
        }

        return $resData;
    }

    /**
     * @param $data
     * @return mixed
     */
    private function addArrayList($data)
    {
        if ($data instanceof CParameter) {
            return [$data->getValue() => $data->getValue()];
        }

        return $data;
    }
}