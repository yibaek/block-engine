<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Items\Element;

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

class SubjectLocality implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-assertion-item-authn-statement-element-subject-locality';
    private $storage;
    private $type;
    private $action;
    private $extra;
    private $address;
    private $dns;

    /**
     * SubjectLocality constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $address
     * @param IBlock|null $dns
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $address = null, IBlock $dns = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->address = $address;
        $this->dns = $dns;
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
        $this->address = $this->setBlock($this->storage, $data['template']['address']);
        $this->dns = $this->setBlock($this->storage, $data['template']['dns']);

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
                'address' => $this->address->getTemplate(),
                'dns' => $this->dns->getTemplate()
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
                'address' => $this->getAddress($blockStorage),
                'dns' => $this->getDns($blockStorage),
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Assertion-Item-AuthnStatement-Element-SubjectLocality'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getAddress(array &$blockStorage): ?string
    {
        $address = $this->address->do($blockStorage);
        if (!is_null($address)) {
            if (!is_string($address)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Item-AuthnStatement-Element-SubjectLocality: Invalid address: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $address;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getDns(array &$blockStorage): ?string
    {
        $dns = $this->dns->do($blockStorage);
        if (!is_null($dns)) {
            if (!is_string($dns)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Item-AuthnStatement-Element-SubjectLocality: Invalid dns: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $dns;
    }
}