<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DArray;

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

class CArrayEqual implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'array-equal';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $array1;
    private $array2;

    /**
     * CArrayEqual constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $array1
     * @param IBlock|null $array2
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $array1 = null, IBlock $array2 = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->array1 = $array1;
        $this->array2 = $array2;
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
        $this->array1 = $this->setBlock($this->storage, $data['template']['array1']);
        $this->array2 = $this->setBlock($this->storage, $data['template']['array2']);

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
                'array1' => $this->array1->getTemplate(),
                'array2' => $this->array2->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return bool
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): bool
    {
        try {
            return serialize($this->getArray1($blockStorage)) === serialize($this->getArray2($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-Array-Equal'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getArray1(array &$blockStorage): array
    {
        $array1 = $this->array1->do($blockStorage);
        if (!is_array($array1)) {
            throw (new InvalidArgumentException('Util-Array-Equal: Invalid array1: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $array1;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getArray2(array &$blockStorage): array
    {
        $array2 = $this->array2->do($blockStorage);
        if (!is_array($array2)) {
            throw (new InvalidArgumentException('Util-Array-Equal: Invalid array2: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $array2;
    }
}