<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Keystore\JWK\Factory;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Authorization\JWT\CreateJWK;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class CreateFromCertificateFile implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'jwt-jwk-factory-certificatefile';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $key;
    private $addValues;

    /**
     * CreateFromCertificateFile constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $key
     * @param IBlock|null $addValues
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $key = null, IBlock $addValues = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->key = $key;
        $this->addValues = $addValues;
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
        $this->addValues = $this->setBlock($this->storage, $data['template']['add-values']);

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
                'add-values' => $this->addValues->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return CreateJWK
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): CreateJWK
    {
        try {
            return (new CreateJWK)->createFromKeyFile($this->getKey($blockStorage), null, $this->getAddValues($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-JWK-Create-Certificatefile'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
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
            throw (new InvalidArgumentException('Authorization-JWK-Create-Certificatefile: Invalid key: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $key;
    }

    /**
     * @param array $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getAddValues(array &$blockStorage): ?array
    {
        $addValues = $this->addValues->do($blockStorage);
        if (!is_null($addValues)) {
            if (!is_array($addValues)) {
                throw (new InvalidArgumentException('Authorization-JWK-Create: Invalid add values: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $addValues;
    }
}