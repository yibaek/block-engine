<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Crypto\RSA;

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

class CRSADecrypt implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'crypto-decrypt-rsa';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $value;
    private $key;
    private $options;

    /**
     * CDecrypt constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $value
     * @param IBlock|null $key
     * @param BlockAggregator|null $options
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $value = null, IBlock $key = null, BlockAggregator $options = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->value = $value;
        $this->key = $key;
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
        $this->value = $this->setBlock($this->storage, $data['template']['value']);
        $this->key = $this->setBlock($this->storage, $data['template']['key']);
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
                'value' => $this->value->getTemplate(),
                'key' => $this->key->getTemplate(),
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
            $isSuccess = openssl_private_decrypt($this->getValue($blockStorage), $decrypt, $this->getKey($blockStorage), $this->makeOption($blockStorage));
            if (false === $isSuccess) {
                $error = openssl_error_string() ?? '';
                throw (new CommonException('Util-Crypto-Decrypt-RSA: '.$error))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
            return $decrypt;
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-Crypto-Decrypt-RSA'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Util-Crypto-Decrypt-RSA: Invalid value: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Util-Crypto-Decrypt-RSA: Invalid key: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $key;
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function makeOption(array &$blockStorage): int
    {
        $options = 1;
        foreach ($this->options as $option) {
            $value = $option->do($blockStorage);
            if (!is_int($value)) {
                throw (new InvalidArgumentException('Util-Crypto-Decrypt-RSA: Invalid option: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
            $options |= $value;
        }

        return $options;
    }
}