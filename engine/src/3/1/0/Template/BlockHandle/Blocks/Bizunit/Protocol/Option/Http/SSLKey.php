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
use Ntuple\Synctree\Util\File\Adapter\IAdapter;
use Throwable;

class SSLKey implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'protocol-option';
    public const ACTION = 'http-ssl-key';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $key;
    private $passwd;

    /**
     * SSLKey constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $key
     * @param IBlock|null $passwd
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $key = null, IBlock $passwd = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->key = $key;
        $this->passwd = $passwd;
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
        $this->key = $this->setBlock($this->storage, $data['template']['key']);
        $this->passwd = $this->setBlock($this->storage, $data['template']['passwd']);

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
                'key' => $this->key->getTemplate(),
                'passwd' => $this->passwd->getTemplate()
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
                'ssl_key' => $this->getCertData($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('HttpOption-SSLKey'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return IAdapter
     * @throws ISynctreeException
     */
    private function getKey(array &$blockStorage): IAdapter
    {
        $key = $this->key->do($blockStorage);
        if (!$key instanceof IAdapter) {
            throw (new InvalidArgumentException('HttpOption-SSLKey: Invalid key: Not a Adapter type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $key;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getPasswd(array &$blockStorage): ?string
    {
        $passwd = $this->passwd->do($blockStorage);
        if (!is_null($passwd)) {
            if (!is_string($passwd)) {
                throw (new InvalidArgumentException('HttpOption-SSLKey: Invalid passwd: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $passwd;
    }

    /**
     * @param array $blockStorage
     * @return array|string
     * @throws ISynctreeException
     */
    private function getCertData(array &$blockStorage)
    {
        if (($passwd=$this->getPasswd($blockStorage)) === null) {
            return ($this->getKey($blockStorage))->getFile();
        }

        return [
            ($this->getKey($blockStorage))->getFile(), $passwd
        ];
    }
}