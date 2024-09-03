<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CPrimitiveManager implements IBlock
{
    public const TYPE = 'primitive';

    private $storage;
    private $block;

    /**
     * CPrimitiveManager constructor.
     * @param PlanStorage $storage
     * @param IBlock|null $block
     */
    public function __construct(PlanStorage $storage, IBlock $block = null)
    {
        $this->storage = $storage;
        $this->block = $block;
    }

    /**
     * @param array $data
     * @return IBlock
     */
    public function setData(array $data): IBlock
    {
        switch ($data['action']) {
            case CString::ACTION:
                $this->block = (new CString($this->storage))->setData($data);
                return $this;

            case CInteger::ACTION:
                $this->block = (new CInteger($this->storage))->setData($data);
                return $this;

            case CBoolean::ACTION:
                $this->block = (new CBoolean($this->storage))->setData($data);
                return $this;

            case CNull::ACTION:
                $this->block = (new CNull($this->storage))->setData($data);
                return $this;

            case CFloat::ACTION:
                $this->block = (new CFloat($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid primitve block action[action:'.$data['action'].']');
        }
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return $this->block->getTemplate();
    }

    /**
     * @param array $blockStorage
     */
    public function do(array &$blockStorage)
    {
        return $this->block->do($blockStorage);
    }
}