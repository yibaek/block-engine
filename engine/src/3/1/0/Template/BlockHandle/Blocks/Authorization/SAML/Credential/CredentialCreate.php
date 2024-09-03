<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Credential;

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

class CredentialCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-credential-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $certificate;
    private $privateKey;

    /**
     * CredentialCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $certificate
     * @param IBlock|null $privateKey
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $certificate = null, IBlock $privateKey = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->certificate = $certificate;
        $this->privateKey = $privateKey;
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
        $this->certificate = $this->setBlock($this->storage, $data['template']['certificate']);
        $this->privateKey = $this->setBlock($this->storage, $data['template']['privatekey']);

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
                'certificate' => $this->certificate->getTemplate(),
                'privatekey' => $this->privateKey->getTemplate()
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
                'load_type' => $this->getLoadType(),
                'cert' => $this->getCertificate($blockStorage),
                'key' => $this->getPrivateKey($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Credential-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getCertificate(array &$blockStorage): string
    {
        $certificate = $this->certificate->do($blockStorage);
        if (!is_string($certificate)) {
            throw (new InvalidArgumentException('Authorization-SAML-Credential-Create: Invalid certificate: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $certificate;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getPrivateKey(array &$blockStorage): ?string
    {
        $privateKey = $this->privateKey->do($blockStorage);
        if (!is_null($privateKey)) {
            if (!is_string($privateKey)) {
                throw (new InvalidArgumentException('Authorization-SAML-Credential-Create: Invalid privateKey: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $privateKey;
    }

    /**
     * @return string
     */
    private function getLoadType(): string
    {
        return 'data';
    }
}