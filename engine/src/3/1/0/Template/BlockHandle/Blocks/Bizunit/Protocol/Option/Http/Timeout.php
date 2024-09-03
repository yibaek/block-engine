<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Option\Http;

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

class Timeout implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'protocol-option';
    public const ACTION = 'http-timeout';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connectTimeout;
    private $maxTimeout;

    /**
     * Timeout constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connectTimeout
     * @param IBlock|null $maxTimeout
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $connectTimeout = null, IBlock $maxTimeout = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->connectTimeout = $connectTimeout;
        $this->maxTimeout = $maxTimeout;
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
        $this->connectTimeout = $this->setBlock($this->storage, $data['template']['connect']);
        $this->maxTimeout = $this->setBlock($this->storage, $data['template']['max']);

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
                'connect' => $this->connectTimeout->getTemplate(),
                'max' => $this->maxTimeout->getTemplate()
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
            return [
                'connect_timeout' => $this->getConnectTimeout($blockStorage),
                'timeout' => $this->getTimeout($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('HttpOption-Timeout'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return float|int
     * @throws ISynctreeException
     */
    private function getConnectTimeout(array &$blockStorage)
    {
        $connectTimeout = $this->connectTimeout->do($blockStorage);
        if (!is_int($connectTimeout) && !is_float($connectTimeout)) {
            throw (new InvalidArgumentException('HttpOption-Timeout: Invalid connect timeout: Not a float or integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $connectTimeout;
    }

    /**
     * @param array $blockStorage
     * @return float|int
     * @throws ISynctreeException
     */
    private function getTimeout(array &$blockStorage)
    {
        $timeout = $this->maxTimeout->do($blockStorage);
        if (!is_int($timeout) && !is_float($timeout)) {
            throw (new InvalidArgumentException('HttpOption-Timeout: Invalid timeout: Not a float or integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $timeout;
    }
}