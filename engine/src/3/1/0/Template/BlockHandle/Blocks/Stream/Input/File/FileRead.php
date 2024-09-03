<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Stream\Input\File;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class FileRead implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'stream';
    public const ACTION = 'file-read';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $filename;
    private $length;

    /**
     * FileRead constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $filename
     * @param IBlock|null $length
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $filename = null, IBlock $length = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->filename = $filename;
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
        $this->filename = $this->setBlock($this->storage, $data['template']['filename']);
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
                'filename' => $this->filename->getTemplate(),
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
            $filename = $this->filename->do($blockStorage);
            return file_get_contents(CommonUtil::getSaveFilePath($filename), false, null, 0, $this->length->do($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Stream-File'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }
}