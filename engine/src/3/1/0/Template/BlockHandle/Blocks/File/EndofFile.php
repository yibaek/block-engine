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
use Ntuple\Synctree\Util\File\Exception\UtilFileException;
use Ntuple\Synctree\Util\File\FileSupport;
use Throwable;

class EndofFile implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'file';
    public const ACTION = 'end-of-file';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $file;
    private $line;

    /**
     * EndofFile constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $file
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $file = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->file = $file;
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
        $this->file = $this->setBlock($this->storage, $data['template']['file']);

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
                'file' => $this->file->getTemplate()
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
            return ($this->getFile($blockStorage))->eof();
        } catch (UtilFileException $ex) {
            throw (new FileException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('File-End-of-File'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return FileSupport
     * @throws ISynctreeException
     */
    private function getFile(array &$blockStorage): FileSupport
    {
        $file = $this->file->do($blockStorage);
        if (!$file instanceof FileSupport) {
            throw (new InvalidArgumentException('File-End-of-File: Invalid file: Not a File type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $file;
    }
}