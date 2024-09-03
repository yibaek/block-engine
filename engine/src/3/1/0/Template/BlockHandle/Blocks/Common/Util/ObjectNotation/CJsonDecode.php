<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\ObjectNotation;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class CJsonDecode implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'object-notation-json-decode';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $value;
    private $options;
    private $depth;

    /**
     * CJsonDecode constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $value
     * @param BlockAggregator|null $options
     * @param IBlock|null $depth
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $value = null, BlockAggregator $options = null, IBlock $depth = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->value = $value;
        $this->options = $options;
        $this->depth = $depth;
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
        $this->value = $this->setBlock($this->storage, $data['template']['value']);
        $this->options = $this->setBlocks($this->storage, $data['template']['options']);
        $this->depth = $this->setBlock($this->storage, $data['template']['depth']);

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
                'value' => $this->value->getTemplate(),
                'options' => $this->getTemplateEachOption(),
                'depth' => $this->depth->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return mixed
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage)
    {
        try {
            return json_decode($this->getValue($blockStorage), true, $this->getDepth($blockStorage), JSON_THROW_ON_ERROR | $this->makeOption($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (\JsonException $ex) {
            throw (new RuntimeException('Util-Json-Decode: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-Json-Decode'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getValue(array &$blockStorage): string
    {
        $value = $this->value->do($blockStorage);
        if (!is_string($value)) {
            throw (new InvalidArgumentException('Util-Json-Decode: Invalid value: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $value;
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function getDepth(array &$blockStorage): int
    {
        $depth = $this->depth->do($blockStorage);
        if (!is_null($depth)) {
            if (!is_int($depth)) {
                throw (new InvalidArgumentException('Util-Json-Decode: Invalid depth: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $depth ?? 512;
    }


    /**
     * @return array
     */
    private function getTemplateEachOption(): array
    {
        $resData = [];
        foreach ($this->options as $option) {
            $resData[] = $option->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function makeOption(array &$blockStorage): int
    {
        $options = 0;
        foreach ($this->options as $option) {
            $value = $option->do($blockStorage);
            if ($value === null || !is_int((int)$value)) {
                throw (new InvalidArgumentException('Util-Json-Decode: Invalid option: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
            $options |= $value;
        }

        return $options;
    }
}