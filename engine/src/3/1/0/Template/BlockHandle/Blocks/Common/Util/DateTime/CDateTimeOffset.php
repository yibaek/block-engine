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

class CDateTimeOffset implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'datetime-offset';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $datetime;

    /**
     * CDateTimeOffset constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $datetime
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $datetime = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->datetime = $datetime;
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
        $this->datetime = $this->setBlock($this->storage, $data['template']['datetime']);

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
                'datetime' => $this->datetime->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): int
    {
        try {
            return ($this->getDateTime($blockStorage))->getOffset();
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-DateTime-Offset'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return DateTimeSupport
     * @throws ISynctreeException
     */
    private function getDateTime(array &$blockStorage): DateTimeSupport
    {
        $datetime = $this->datetime->do($blockStorage);
        if (!$datetime instanceof DateTimeSupport) {
            throw (new InvalidArgumentException('Util-DateTime-Offset: Invalid datetime'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $datetime;
    }
}