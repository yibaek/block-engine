<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\System\File;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Exceptions\SystemException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\System\Exception\UtilSystemException;
use Ntuple\Synctree\Util\System\File\FileSystemSupport;
use Throwable;

class Move implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'system';
    public const ACTION = 'file-move';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $source;
    private $destination;
    private $overwrite;

    /**
     * Move constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $source
     * @param IBlock|null $destination
     * @param IBlock|null $overwrite
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $source = null, IBlock $destination = null, IBlock $overwrite = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->source = $source;
        $this->destination = $destination;
        $this->overwrite = $overwrite;
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
        $this->source = $this->setBlock($this->storage, $data['template']['source']);
        $this->destination = $this->setBlock($this->storage, $data['template']['destination']);
        $this->overwrite = $this->setBlock($this->storage, $data['template']['overwrite']);

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
                'source' => $this->source->getTemplate(),
                'destination' => $this->destination->getTemplate(),
                'overwrite' => $this->overwrite->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return void
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): void
    {
        try {
            (new FileSystemSupport())->move($this->getSource($blockStorage), $this->getDestination($blockStorage), $this->getOverWrite($blockStorage));
        } catch (UtilSystemException $ex) {
            throw (new SystemException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('System-File-Move'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getSource(array &$blockStorage): string
    {
        $source = $this->source->do($blockStorage);
        if (!is_string($source)) {
            throw (new InvalidArgumentException('System-File-Move: Invalid source: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $source;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getDestination(array &$blockStorage): string
    {
        $destination = $this->destination->do($blockStorage);
        if (!is_string($destination)) {
            throw (new InvalidArgumentException('System-File-Move: Invalid destination: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $destination;
    }

    /**
     * @param array $blockStorage
     * @return bool
     * @throws ISynctreeException
     */
    private function getOverWrite(array &$blockStorage): bool
    {
        $overwrite = $this->overwrite->do($blockStorage);
        if (is_null($overwrite)) {
            return false;
        }

        if (!is_bool($overwrite)) {
            throw (new InvalidArgumentException('System-File-Move: Invalid overwrite: Not a boolean type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $overwrite;
    }
}