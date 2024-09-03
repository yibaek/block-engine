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
use Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive\CNull;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\File\Adapter\IAdapter;
use Ntuple\Synctree\Util\File\Exception\UtilFileException;
use Ntuple\Synctree\Util\File\FileSupport;
use Throwable;

class FilePointer implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'file';
    public const ACTION = 'pointer';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $adapter;
    private $mode;

    /**
     * FilePointer constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $adapter
     * @param IBlock|null $mode
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $adapter = null, IBlock $mode = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->adapter = $adapter;
        $this->mode = $mode ?? $this->getDefaultBlock();
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
        $this->mode = isset($data['template']['mode']) ?$this->setBlock($this->storage, $data['template']['mode']) :$this->getDefaultBlock();

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
                'mode' => $this->mode->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return FileSupport
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): FileSupport
    {
        try {
            return (new FileSupport($this->getAdapter($blockStorage)))->createPointer($this->getMode($blockStorage));
        } catch (UtilFileException $ex) {
            throw (new FileException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('File-Pointer'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('File-Pointer: Invalid adapter: Not a Adapter type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $adapter;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getMode(array &$blockStorage): string
    {
        $mode = $this->mode->do($blockStorage);
        if (is_null($mode)) {
            return 'rb';
        }

        if (!is_string($mode)) {
            throw (new InvalidArgumentException('File-Pointer: Invalid mode: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $mode;
    }

    /**
     * @return CNull
     */
    private function getDefaultBlock(): CNull
    {
        return new CNull($this->storage, $this->extra);
    }
}