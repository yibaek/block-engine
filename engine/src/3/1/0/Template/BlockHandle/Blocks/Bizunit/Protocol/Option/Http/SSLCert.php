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

class SSLCert implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'protocol-option';
    public const ACTION = 'http-ssl-cert';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $cert;
    private $passwd;

    /**
     * SSLCert constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $cert
     * @param IBlock|null $passwd
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $cert = null, IBlock $passwd = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->cert = $cert;
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
        $this->cert = $this->setBlock($this->storage, $data['template']['cert']);
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
                'cert' => $this->cert->getTemplate(),
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
                'cert' => $this->getCertData($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('HttpOption-SSLCert'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return IAdapter
     * @throws ISynctreeException
     */
    private function getCert(array &$blockStorage): IAdapter
    {
        $cert = $this->cert->do($blockStorage);
        if (!$cert instanceof IAdapter) {
            throw (new InvalidArgumentException('HttpOption-SSLCert: Invalid cert: Not a Adapter type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $cert;
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
                throw (new InvalidArgumentException('HttpOption-SSLCert: Invalid passwd: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            return ($this->getCert($blockStorage))->getFile();
        }

        return [
            ($this->getCert($blockStorage))->getFile(), $passwd
        ];
    }
}