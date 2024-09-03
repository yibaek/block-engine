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

class CDateTimeFormat implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'datetime-format';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $datetime;
    private $format;

    /**
     * CDateTimeFormat constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $datetime
     * @param IBlock|null $format
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $datetime = null, IBlock $format = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->datetime = $datetime;
        $this->format = $format;
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
        $this->format = $this->setBlock($this->storage, $data['template']['format']);

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
                'datetime' => $this->datetime->getTemplate(),
                'format' => $this->format->getTemplate()
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
            return ($this->getDateTime($blockStorage))->format($this->getFormat($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-DateTime-Format'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Util-DateTime-Format: Invalid datetime'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $datetime;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getFormat(array &$blockStorage): string
    {
        $format = $this->format->do($blockStorage);
        if (!is_string($format)) {
            throw (new InvalidArgumentException('Util-DateTime-Format: Invalid format: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $format;
    }
}