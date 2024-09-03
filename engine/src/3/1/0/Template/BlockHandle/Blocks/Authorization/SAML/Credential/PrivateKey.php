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

class PrivateKey implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-credential-privatekey';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $value;

    /**
     * PrivateKey constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $value
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $value = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->value = $value;
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
                'value' => $this->value->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): ?string
    {
        try {
            return $this->getValue($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Credential-PrivateKey'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getValue(array &$blockStorage): ?string
    {
        $value = $this->value->do($blockStorage);
        if (!is_null($value)) {
            if (!is_string($value)) {
                throw (new InvalidArgumentException('Authorization-SAML-Credential-PrivateKey: Invalid value: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $value;
    }
}