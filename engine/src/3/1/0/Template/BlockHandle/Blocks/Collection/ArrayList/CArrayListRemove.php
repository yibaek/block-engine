<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\ArrayList;

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

class CArrayListRemove implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'arraylist';
    public const ACTION = 'remove';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $arrayName;
    private $keysName;

    /**
     * CArrayListRemove constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $arrayName
     * @param BlockAggregator|null $keysName
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $arrayName = null, BlockAggregator $keysName = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->arrayName = $arrayName;
        $this->keysName = $keysName;
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
                'keys' => $this->getTemplateEachKey()
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

            // get arrayList keyName
            $arrayName = $this->getArrayName($blockStorage);

            // check stack first;
            foreach($this->storage->getStackManager()->toArray() as $stack) {
                if (array_key_exists($arrayName, $stack->data)) {
                    if (!is_array($stack->data[$arrayName])) {
                        throw (new InvalidArgumentException('ArrayList-Remove: Not a array type: '.$arrayName))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
                    }
                    $this->removeValue($stack->data[$arrayName], $keys);
                    return;
                }
            }

            if (!array_key_exists($arrayName, $blockStorage)) {
                throw (new InvalidArgumentException('ArrayList-Remove: Not found data: '.$arrayName))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            if (!is_array($blockStorage[$arrayName])) {
                throw (new InvalidArgumentException('ArrayList-Remove: Not a array type: '.$arrayName))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            $this->removeValue($blockStorage[$arrayName], $keys);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('ArrayList-Remove'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('ArrayList-Remove: Invalid target key: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('ArrayList-Remove: Invalid item key: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $data
     * @param $keys
     * @throws ISynctreeException
     */
    private function removeValue(array &$data, $keys): void
    {
        if (count($keys) === 1) {
            $key = current($keys);
            if (!array_key_exists($key, $data)) {
                throw (new InvalidArgumentException('ArrayList-Remove: Not found data: '.$key))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            unset($data[$key]);
            $data = array_values($data);
        } else {
            $key = array_shift($keys);
            if (!array_key_exists($key, $data)) {
                throw (new InvalidArgumentException('ArrayList-Remove: Not found data: '.$key))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            if (!is_array($data[$key])) {
                throw (new InvalidArgumentException('ArrayList-Remove: Not a array type: '.$key))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            $this->removeValue($data[$key], $keys);
        }
    }
}