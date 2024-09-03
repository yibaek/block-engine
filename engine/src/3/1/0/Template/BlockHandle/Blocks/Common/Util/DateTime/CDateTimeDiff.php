<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime;

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
use Ntuple\Synctree\Util\Support\DateTime\DateTimeSupport;
use Throwable;

class CDateTimeDiff implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'datetime-diff';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $datetime1;
    private $datetime2;

    /**
     * CDateTimeDiff constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $datetime1
     * @param IBlock|null $datetime2
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $datetime1 = null, IBlock $datetime2 = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->datetime1 = $datetime1;
        $this->datetime2 = $datetime2;
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
        $this->datetime1 = $this->setBlock($this->storage, $data['template']['datetime1']);
        $this->datetime2 = $this->setBlock($this->storage, $data['template']['datetime2']);

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
                'datetime1' => $this->datetime1->getTemplate(),
                'datetime2' => $this->datetime2->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return DateTimeSupport
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): DateTimeSupport
    {
        try {
            return ($this->getDateTime1($blockStorage))->diff($this->getDateTime2($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-DateTime-Diff'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return DateTimeSupport
     * @throws ISynctreeException
     */
    private function getDateTime1(array &$blockStorage): DateTimeSupport
    {
        $datetime1 = $this->datetime1->do($blockStorage);
        if (!$datetime1 instanceof DateTimeSupport) {
            throw (new InvalidArgumentException('Util-DateTime-Diff: Invalid datetime1'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $datetime1;
    }

    /**
     * @param array $blockStorage
     * @return DateTimeSupport
     * @throws ISynctreeException
     */
    private function getDateTime2(array &$blockStorage): DateTimeSupport
    {
        $datetime2 = $this->datetime2->do($blockStorage);
        if (!$datetime2 instanceof DateTimeSupport) {
            throw (new InvalidArgumentException('Util-DateTime-Diff: Invalid datetime2'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $datetime2;
    }
}