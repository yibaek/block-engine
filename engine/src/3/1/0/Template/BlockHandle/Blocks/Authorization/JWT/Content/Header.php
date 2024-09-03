<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Content;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class Header implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'jwt-content-header';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $algo;

    /**
     * Header constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param BlockAggregator|null $algo
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, BlockAggregator $algo = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->algo = $algo;
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
        $this->algo = $this->setBlocks($this->storage, $data['template']['algo']);

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
                'algo' => $this->getTemplateEachAlgo()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array[]
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): array
    {
        try {
            return [
                'alg' => $this->getAlgo($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-JWT-Content-Header'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachAlgo(): array
    {
        $resData = [];
        foreach ($this->algo as $algo) {
            $resData[] = $algo->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getAlgo(array &$blockStorage): array
    {
        $resData = [];
        foreach ($this->algo as $algo) {
            $algorithm = $algo->do($blockStorage);
            if (!is_string($algorithm)) {
                throw (new InvalidArgumentException('Authorization-JWT-Content-Header: Invalid algorithm: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
            $resData[] = $algorithm;
        }

        return $resData;
    }
}