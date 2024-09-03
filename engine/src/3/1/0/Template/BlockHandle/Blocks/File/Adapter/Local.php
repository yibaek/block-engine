<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\File\Adapter;

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
use Ntuple\Synctree\Util\File\Adapter\Local as UtilLocal;
use Ntuple\Synctree\Util\File\Exception\UtilFileException;
use Throwable;

class Local implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'file';
    public const ACTION = 'adapter-local';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $filename;

    /**
     * Local constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $filename
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $filename = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->filename = $filename;
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
        $this->filename = $this->setBlock($this->storage, $data['template']['filename']);

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
                'filename' => $this->filename->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return IAdapter
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): IAdapter
    {
        try {
            return (new UtilLocal($this->getFileName($blockStorage)));
        } catch (UtilFileException $ex) {
            throw (new FileException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('File-Adapter-Local'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getFileName(array &$blockStorage): string
    {
        $filename = $this->filename->do($blockStorage);
        if (!is_string($filename)) {
            throw (new InvalidArgumentException('File-Adapter-Local: Invalid filename: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $filename;
    }
}