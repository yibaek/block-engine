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

class CArrayListGet implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'arraylist';
    public const ACTION = 'get';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $arrayName;
    private $keysName;

    /**
     * CArrayListGet constructor.
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
     * @return mixed
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage)
    {
        try {
            $data = null;

            // get arrayList keyName
            $arrayName = $this->getArrayName($blockStorage);

            // check stack first;
            foreach($this->storage->getStackManager()->toArray() as $stack) {
                if (array_key_exists($arrayName, $stack->data)) {
                    $data = $stack->data[$arrayName];
                    if (!is_array($data)) {
                        throw (new InvalidArgumentException('ArrayList-Get: Not a array type: '.$arrayName))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
                    }
                    break;
                }
            }

            if ($data === null) {
                if (!array_key_exists($arrayName, $blockStorage)) {
                    throw (new InvalidArgumentException('ArrayList-Get: Not found data: '.$arrayName))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
                }

                $data = $blockStorage[$arrayName];
                if (!is_array($data)) {
                    throw (new InvalidArgumentException('ArrayList-Get: Not a array type: '.$arrayName))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
                }
            }

            $previousKeyName = '';
            foreach ($this->keysName as $keyName) {
                if (!is_array($data)) {
                    throw (new InvalidArgumentException('ArrayList-Get: Not a array type: '.$previousKeyName))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
                }

                $key = $this->getItemKey($blockStorage, $keyName);
                $data = $this->getValue($data, $key);
                $previousKeyName = $key;
            }

            return $data;
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('ArrayList-Get'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('ArrayList-Get: Invalid target key: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('ArrayList-Get: Invalid item key: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $data
     * @param string $key
     * @return mixed
     * @throws ISynctreeException
     */
    private function getValue(array $data, $key)
    {
        if (!array_key_exists($key, $data)) {
            throw (new InvalidArgumentException('ArrayList-Get: Not found data: '.$key))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $data[$key];
    }
}