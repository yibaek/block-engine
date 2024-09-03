<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Exception\Handler;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\BizunitResponseException;
use Ntuple\Synctree\Exceptions\Inner\DebugBreakPointException;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class TryCatchCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'exception-handler';
    public const ACTION = 'try-catch';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $try;
    private $catchers;

    /**
     * TryCatchCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param BlockAggregator|null $try
     * @param BlockAggregator|null $catchers
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, BlockAggregator $try = null, BlockAggregator $catchers = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->try = $try;
        $this->catchers = $catchers;
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
        $this->try = $this->setBlocks($this->storage, $data['template']['try']);
        $this->catchers = $this->setBlocks($this->storage, $data['template']['catchers']);

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
                'try' => $this->getTemplateEachTryStatement(),
                'catchers' => $this->getTemplateEachCatcher()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): void
    {
        try {
            // do statements
            foreach ($this->try as $statement) {
                $statement->do($blockStorage);
            }
        // inner exception
        } catch (BizunitResponseException | DebugBreakPointException $ex) {
            throw $ex;
        } catch (SynctreeException $ex) {
            try {
                if (!empty($this->catchers)) {
                    $isCatch = false;
                    foreach ($this->catchers as $catcher) {
                        $catcher->setException($ex);
                        if (true === ($isCatch=$catcher->do($blockStorage))) {
                            break;
                        }
                    }

                    if (true === $isCatch) {
                        throw new BizunitResponseException(400, [], []);
                    }
                }
                throw $ex;
            } catch (SynctreeException|SynctreeInnerException $ex) {
                throw $ex;
            } catch (Throwable $ex) {
                $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
                throw (new RuntimeException('TryCatch'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachTryStatement(): array
    {
        $resData = [];
        foreach ($this->try as $statement) {
            $resData[] = $statement->getTemplate();
        }

        return $resData;
    }

    /**
     * @return array
     */
    private function getTemplateEachCatcher(): array
    {
        $resData = [];
        foreach ($this->catchers as $catcher) {
            $resData[] = $catcher->getTemplate();
        }

        return $resData;
    }
}