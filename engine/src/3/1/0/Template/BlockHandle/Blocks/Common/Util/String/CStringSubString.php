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
use Ntuple\Synctree\Util\ValidationUtil;
use Throwable;

class CStringSubString implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'string-substring';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $target;
    private $start;
    private $length;
    private $encoding;

    /**
     * CStringSubString constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $target
     * @param IBlock|null $start
     * @param IBlock|null $length
     * @param IBlock|null $encoding
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $target = null, IBlock $start = null, IBlock $length = null, IBlock $encoding = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->target = $target;
        $this->start = $start;
        $this->length = $length;
        $this->encoding = $encoding;
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
        $this->start = $this->setBlock($this->storage, $data['template']['start']);
        $this->length = $this->setBlock($this->storage, $data['template']['length']);
        $this->encoding = $this->setBlock($this->storage, $data['template']['encoding']);

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
                'start' => $this->start->getTemplate(),
                'length' => $this->length->getTemplate(),
                'encoding' => $this->encoding->getTemplate()
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
            return mb_substr($this->getTarget($blockStorage), $this->getStart($blockStorage), $this->getLength($blockStorage), $this->getEncoding($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-String-Substring'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getTarget(array &$blockStorage): string
    {
        try {
            return ValidationUtil::isConvertStringType($this->target->do($blockStorage));
        } catch (\InvalidArgumentException $ex) {
            throw (new InvalidArgumentException('Util-String-Substring: Invalid target: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getEncoding(array &$blockStorage): string
    {
        $encoding = $this->encoding->do($blockStorage);
        if (!is_string($encoding)) {
            throw (new InvalidArgumentException('Util-String-Substring: Invalid encoding: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $encoding;
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function getStart(array &$blockStorage): int
    {
        $start = $this->start->do($blockStorage);
        if (!is_int($start)) {
            throw (new InvalidArgumentException('Util-String-Substring: Invalid start: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $start;
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getLength(array &$blockStorage): ?int
    {
        $length = $this->length->do($blockStorage);
        if (!is_null($length)) {
            if (!is_int($length)) {
                throw (new InvalidArgumentException('Util-String-Substring: Invalid length: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $length;
    }
}