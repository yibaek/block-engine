<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\HashMap;

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
use Ntuple\Synctree\Util\ValidationUtil;
use Throwable;

class CHashMapAdd implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'hashmap';
    public const ACTION = 'add';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $arrayName;
    private $keysName;
    private $value;

    /**
     * CHashMapAdd constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $arrayName
     * @param BlockAggregator|null $keysName
     * @param IBlock|null $value
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $arrayName = null, BlockAggregator $keysName = null, IBlock $value = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->arrayName = $arrayName;
        $this->keysName = $keysName;
        $this->value = $value;
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
        $this->arrayName = $this->setBlock($this->storage, $data['template']['array']);
        $this->keysName = $this->setBlocks($this->storage, $data['template']['keys']);
        $this->value = $this->setBlock($this->storage, $data['template']['value']);

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
                'array' => $this->arrayName->getTemplate(),
                'keys' => $this->getTemplateEachKey(),
                'value' => $this->value->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): void
    {
        try {
            $keys = [];
            foreach ($this->keysName as $keyName) {
                $keys[] = $this->getItemKey($blockStorage, $keyName);
            }

            // get put data
            $putData = $this->value->do($blockStorage);
            if (!is_array($putData)) {
                throw (new InvalidArgumentException('HashMap-Add: Value is Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            // get hashMap keyName
            $arrayName = $this->getArrayName($blockStorage);

            // check stack first;
            foreach($this->storage->getStackManager()->toArray() as $stack) {
                if (array_key_exists($arrayName, $stack->data)) {
                    if (!is_array($stack->data[$arrayName])) {
                        throw (new InvalidArgumentException('HashMap-Add: Not a array type: '.$arrayName))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
                    }
                    $stack->data[$arrayName] = $this->addValue($stack->data[$arrayName], $keys, $putData);
                    return;
                }
            }

            if (!array_key_exists($arrayName, $blockStorage)) {
                throw (new InvalidArgumentException('HashMap-Add: Not found data: '.$arrayName))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            if (!is_array($blockStorage[$arrayName])) {
                throw (new InvalidArgumentException('HashMap-Add: Not a array type: '.$arrayName))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            $blockStorage[$arrayName] = $this->addValue($blockStorage[$arrayName], $keys, $putData);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('HashMap-Add'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachKey(): array
    {
        $resData = [];
        foreach ($this->keysName as $value) {
            $resData[] = $value->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return int|string
     * @throws ISynctreeException
     */
    private function getArrayName(array &$blockStorage)
    {
        try {
            return ValidationUtil::validateArrayKey($this->arrayName->do($blockStorage));
        } catch (\InvalidArgumentException $ex) {
            throw (new InvalidArgumentException('HashMap-Add: Invalid target key: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @param $keyName
     * @return int|string
     * @throws ISynctreeException
     */
    private function getItemKey(array &$blockStorage, $keyName)
    {
        try {
            return ValidationUtil::validateArrayKey($keyName->do($blockStorage));
        } catch (\InvalidArgumentException $ex) {
            throw (new InvalidArgumentException('HashMap-Add: Invalid item key: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $data
     * @param $keys
     * @param array $putData
     * @return mixed
     * @throws ISynctreeException
     */
    private function addValue(array $data, $keys, array $putData)
    {
        if (is_array($keys) && !empty($keys)) {
            $key = array_shift($keys);

            if (!array_key_exists($key, $data)) {
                throw (new InvalidArgumentException('HashMap-Add: Not found data: '.$key))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            if (!is_array($data[$key])) {
                throw (new InvalidArgumentException('HashMap-Add: Not a array type: '.$key))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            $data[$key] = $this->addValue($data[$key], $keys, $putData);
        } else {
            foreach ($putData as $putKey => $putValue) {
                $data[$putKey] = $putValue;
            }
        }

        return $data;
    }
}