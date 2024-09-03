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

class CStringSplit implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'string-split';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $target;
    private $delimiter;
    private $limit;
    private $encoding;

    /**
     * CStringSplit constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $target
     * @param IBlock|null $delimiter
     * @param IBlock|null $limit
     * @param IBlock|null $encoding
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $target = null, IBlock $delimiter = null, IBlock $limit = null, IBlock $encoding = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->target = $target;
        $this->delimiter = $delimiter;
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
        $this->delimiter = $this->setBlock($this->storage, $data['template']['delimiter']);
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
                'delimiter' => $this->delimiter->getTemplate(),
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
                ->split($this->getDelimiter($blockStorage), $this->getLimit($blockStorage));
            if (false === $resData) {
                throw (new RuntimeException('Util-String-Split: Invalid target or delimiter: False'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            return $resData;
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-String-Split'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Util-String-Split: Invalid target: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Util-String-Split: Invalid encoding: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $encoding;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getDelimiter(array &$blockStorage): string
    {
        $delimiter = $this->delimiter->do($blockStorage);
        if (!is_string($delimiter)) {
            throw (new InvalidArgumentException('Util-String-Split: Invalid delimiter: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $delimiter;
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
                throw (new InvalidArgumentException('Util-String-Split: Invalid limit: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $limit ?? -1;
    }
}