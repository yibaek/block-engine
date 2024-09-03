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

class CDateTimeCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'datetime-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $time;
    private $timezone;

    /**
     * CDateTimeCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $time
     * @param IBlock|null $timezone
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $time = null, IBlock $timezone = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->time = $time;
        $this->timezone = $timezone;
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
        $this->time = $this->setBlock($this->storage, $data['template']['time']);
        $this->timezone = $this->setBlock($this->storage, $data['template']['timezone']);

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
                'time' => $this->time->getTemplate(),
                'timezone' => $this->timezone->getTemplate()
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
            return new DateTimeSupport($this->getTime($blockStorage), $this->getTimezone($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-DateTime-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getTime(array &$blockStorage): ?string
    {
        $time = $this->time->do($blockStorage);
        if (!is_null($time)) {
            if (!is_string($time)) {
                throw (new InvalidArgumentException('Util-DateTime-Create: Invalid time: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $time;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getTimezone(array &$blockStorage): ?string
    {
        $timezone = $this->timezone->do($blockStorage);
        if (!is_null($timezone)) {
            if (!is_string($timezone)) {
                throw (new InvalidArgumentException('Util-DateTime-Create: Invalid timezone: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $timezone ?? date_default_timezone_get();
    }
}