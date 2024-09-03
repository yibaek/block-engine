<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String;

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
use Throwable;

class CStringCharsetEncode implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'string-charset-encode';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $target;
    private $from;
    private $to;
    private $option;

    /**
     * CStringCharsetEncode constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $target
     * @param IBlock|null $from
     * @param IBlock|null $to
     * @param IBlock|null $option
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $target = null, IBlock $from = null, IBlock $to = null, IBlock $option = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->target = $target;
        $this->from = $from;
        $this->to = $to;
        $this->option = $option;
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
        $this->target = $this->setBlock($this->storage, $data['template']['target']);
        $this->from = $this->setBlock($this->storage, $data['template']['from']);
        $this->to = $this->setBlock($this->storage, $data['template']['to']);
        $this->option = $this->setBlock($this->storage, $data['template']['option']);

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
                'target' => $this->target->getTemplate(),
                'from' => $this->from->getTemplate(),
                'to' => $this->to->getTemplate(),
                'option' => $this->option->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return float|int
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage)
    {
        try {
            return iconv($this->getFrom($blockStorage), $this->getTo($blockStorage), $this->getTarget($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-String-Charset-Encode'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getFrom(array &$blockStorage): string
    {
        $from = $this->from->do($blockStorage);
        if (!is_string($from)) {
            throw (new InvalidArgumentException('Util-String-Charset-Encode: Invalid from: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $from;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getTo(array &$blockStorage): string
    {
        $to = $this->to->do($blockStorage);
        if (!is_string($to)) {
            throw (new InvalidArgumentException('Util-String-Charset-Encode: Invalid to: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $to.$this->getOption($blockStorage);
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getTarget(array &$blockStorage): string
    {
        $target = $this->target->do($blockStorage);
        if (!is_string($target)) {
            throw (new InvalidArgumentException('Util-String-Charset-Encode: Invalid target: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $target;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getOption(array &$blockStorage): string
    {
        $option = $this->option->do($blockStorage);
        if (is_null($option)) {
            return '//IGNORE';
        }

        if (!is_string($option)) {
            throw (new InvalidArgumentException('Util-String-Charset-Encode: Invalid option: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        if (!in_array($option, ['//TRANSLIT', '//IGNORE,', '//TRANSLIT//IGNORE'], true)) {
            throw (new InvalidArgumentException('Util-String-Charset-Encode: Invalid option: Not supported: '.$option))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $option;
    }
}