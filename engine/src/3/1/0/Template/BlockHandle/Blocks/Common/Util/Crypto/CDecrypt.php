<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Crypto;

use Exception;
use Ntuple\Synctree\Exceptions\CommonException;
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

class CDecrypt implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'crypto-decrypt';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $method;
    private $value;
    private $key;
    private $iv;
    private $options;

    /**
     * CDecrypt constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $method
     * @param IBlock|null $value
     * @param IBlock|null $key
     * @param IBlock|null $iv
     * @param BlockAggregator|null $options
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $method = null, IBlock $value = null, IBlock $key = null, IBlock $iv = null, BlockAggregator $options = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->method = $method;
        $this->value = $value;
        $this->key = $key;
        $this->iv = $iv;
        $this->options = $options;
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
        $this->method = $this->setBlock($this->storage, $data['template']['method']);
        $this->value = $this->setBlock($this->storage, $data['template']['value']);
        $this->key = $this->setBlock($this->storage, $data['template']['key']);
        $this->iv = $this->setBlock($this->storage, $data['template']['iv']);
        $this->options = $this->setBlocks($this->storage, $data['template']['options']);

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
                'method' => $this->method->getTemplate(),
                'value' => $this->value->getTemplate(),
                'key' => $this->key->getTemplate(),
                'iv' => $this->iv->getTemplate(),
                'options' => $this->getTemplateEachOption()
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
            $decrypt = openssl_decrypt($this->getValue($blockStorage), $this->getMethod($blockStorage), $this->getKey($blockStorage), $this->makeOption($blockStorage), $this->getIv($blockStorage));
            if (false === $decrypt) {
                $error = openssl_error_string() ?? '';
                throw (new CommonException('Util-Crypt-Decrypt: '.$error))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
            return $decrypt;
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-Crypt-Decrypt'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachOption(): array
    {
        $resData = [];
        foreach ($this->options as $option) {
            $resData[] = $option->getTemplate();
        }

        return $resData;
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
            throw (new InvalidArgumentException('Util-Crypt-Decrypt: Invalid value: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $value;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getMethod(array &$blockStorage): string
    {
        $method = $this->method->do($blockStorage);
        if (!is_string($method)) {
            throw (new InvalidArgumentException('Util-Crypt-Decrypt: Invalid method: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $method;
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
            throw (new InvalidArgumentException('Util-Crypt-Decrypt: Invalid key: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $key;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getIv(array &$blockStorage): string
    {
        $iv = $this->iv->do($blockStorage);

        if (empty($iv)) {
            return '';
        }

        if (!is_string($iv)) {
            throw (new InvalidArgumentException('Util-Crypt-Decrypt: Invalid iv: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $iv;
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function makeOption(array &$blockStorage): int
    {
        $options = 0;
        foreach ($this->options as $option) {
            $value = $option->do($blockStorage);
            if (!is_int($value)) {
                throw (new InvalidArgumentException('Util-Crypt-Decrypt: Invalid option: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
            $options |= $value;
        }

        return $options;
    }
}