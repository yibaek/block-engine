<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\File;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\FileException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\File\Adapter\IAdapter;
use Ntuple\Synctree\Util\File\Exception\UtilFileException;
use Ntuple\Synctree\Util\File\FileSupport;
use Throwable;

class ReadAll implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'file';
    public const ACTION = 'read-all';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $adapter;
    private $offset;
    private $length;

    /**
     * ReadAll constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $adapter
     * @param IBlock|null $offset
     * @param IBlock|null $length
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $adapter = null, IBlock $offset = null, IBlock $length = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->adapter = $adapter;
        $this->offset = $offset;
        $this->length = $length;
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
        $this->adapter = $this->setBlock($this->storage, $data['template']['adapter']);
        $this->offset = $this->setBlock($this->storage, $data['template']['offset']);
        $this->length = $this->setBlock($this->storage, $data['template']['length']);

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
                'adapter' => $this->adapter->getTemplate(),
                'offset' => $this->offset->getTemplate(),
                'length' => $this->length->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): string
    {
        try {
            return FileSupport::readAll($this->getAdapter($blockStorage), $this->getOffset($blockStorage), $this->getLength($blockStorage));
        } catch (UtilFileException $ex) {
            throw (new FileException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('File-Read-All'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return IAdapter
     * @throws ISynctreeException
     */
    private function getAdapter(array &$blockStorage): IAdapter
    {
        $adapter = $this->adapter->do($blockStorage);
        if (!$adapter instanceof IAdapter) {
            throw (new InvalidArgumentException('File-Read-All: Invalid adapter: Not a Adapter type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $adapter;
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getOffset(array &$blockStorage): ?int
    {
        $offset = $this->offset->do($blockStorage);
        if (!is_null($offset)) {
            if (!is_int($offset)) {
                throw (new InvalidArgumentException('File-Read-All: Invalid offset: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $offset ?? 0;
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getLength(array &$blockStorage): ?int
    {
        $length = $this->length->do($blockStorage);
        if (!is_null($length)) {
            if (!is_int($length)) {
                throw (new InvalidArgumentException('File-Read-All: Invalid length: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $length;
    }
}