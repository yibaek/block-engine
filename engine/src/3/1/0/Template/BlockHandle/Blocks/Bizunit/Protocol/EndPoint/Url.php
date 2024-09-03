<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\EndPoint;

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

class Url implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'protocol-end-point';
    public const ACTION = 'url';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $endPoint;

    /**
     * Url constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $endPoint
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $endPoint = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->endPoint = $endPoint;
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
        $this->endPoint = $this->setBlock($this->storage, $data['template']['end-point']);

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
                'end-point' => $this->endPoint->getTemplate()
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
            return $this->getEndpoint($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Url'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getEndpoint(array &$blockStorage): string
    {
        $endpoint = $this->endPoint->do($blockStorage);
        if (!is_string($endpoint)) {
            throw (new InvalidArgumentException('Url: Invalid endpoint: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $endpoint;
    }
}