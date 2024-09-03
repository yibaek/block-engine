<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Hash;

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

class CHashHmacGenerate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'hash-hashhmac-generate';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $value;
    private $algo;
    private $key;

    /**
     * CHashHmacGenerate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $value
     * @param IBlock|null $algo
     * @param IBlock|null $key
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $value = null, IBlock $algo = null, IBlock $key = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->value = $value;
        $this->algo = $algo;
        $this->key = $key;
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
        $this->value = $this->setBlock($this->storage, $data['template']['value']);
        $this->algo = $this->setBlock($this->storage, $data['template']['algo']);
        $this->key = $this->setBlock($this->storage, $data['template']['key']);

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
                'value' => $this->value->getTemplate(),
                'algo' => $this->algo->getTemplate(),
                'key' => $this->key->getTemplate()
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
            return hash_hmac($this->getAlgorithm($blockStorage), $this->getValue($blockStorage), $this->getKey($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-Hash-Hmac'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getValue(array &$blockStorage): string
    {
        $value = $this->value->do($blockStorage);
        if (!is_string($value)) {
            throw (new InvalidArgumentException('Util-Hash-Hmac: Invalid value: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $value;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getKey(array &$blockStorage): string
    {
        $key = $this->key->do($blockStorage);
        if (!is_string($key)) {
            throw (new InvalidArgumentException('Util-Hash-Hmac: Invalid key: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $key;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getAlgorithm(array &$blockStorage): string
    {
        $algorithm = $this->algo->do($blockStorage);
        if (!is_string($algorithm)) {
            throw (new InvalidArgumentException('Util-Hash-Hmac: Invalid algorithm: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        if (!in_array($algorithm, hash_hmac_algos(), true)) {
            throw (new InvalidArgumentException('Util-Hash-Hmac: Invalid algorithm: Not supported: '.$algorithm))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $algorithm;
    }
}