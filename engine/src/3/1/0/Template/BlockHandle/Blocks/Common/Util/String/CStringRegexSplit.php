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
use Ntuple\Synctree\Util\Support\String\StringSupport;
use Ntuple\Synctree\Util\ValidationUtil;
use Throwable;

class CStringRegexSplit implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'string-regex-split';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $target;
    private $pattern;
    private $limit;
    private $encoding;

    /**
     * CStringRegexSplit constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $target
     * @param IBlock|null $pattern
     * @param IBlock|null $limit
     * @param IBlock|null $encoding
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $target = null, IBlock $pattern = null, IBlock $limit = null, IBlock $encoding = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->target = $target;
        $this->pattern = $pattern;
        $this->limit = $limit;
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
        $this->pattern = $this->setBlock($this->storage, $data['template']['pattern']);
        $this->limit = $this->setBlock($this->storage, $data['template']['limit']);
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
                'pattern' => $this->pattern->getTemplate(),
                'limit' => $this->limit->getTemplate(),
                'encoding' => $this->encoding->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): array
    {
        try {
            $resData = (new StringSupport($this->getTarget($blockStorage), $this->getEncoding($blockStorage)))
                ->regexSplit($this->getPattern($blockStorage), $this->getLimit($blockStorage));
            if (false === $resData) {
                throw (new RuntimeException('Util-String-RegexSplit: Invalid target or pattern: False'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            return $resData;
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-String-RegexSplit'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Util-String-RegexSplit: Invalid target: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Util-String-RegexSplit: Invalid encoding: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $encoding;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getPattern(array &$blockStorage): string
    {
        $pattern = $this->pattern->do($blockStorage);
        if (!is_string($pattern)) {
            throw (new InvalidArgumentException('Util-String-RegexSplit: Invalid pattern: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $pattern;
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function getLimit(array &$blockStorage): int
    {
        $limit = $this->limit->do($blockStorage);
        if (!is_null($limit)) {
            if (!is_int($limit)) {
                throw (new InvalidArgumentException('Util-String-RegexSplit: Invalid limit: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $limit ?? -1;
    }
}