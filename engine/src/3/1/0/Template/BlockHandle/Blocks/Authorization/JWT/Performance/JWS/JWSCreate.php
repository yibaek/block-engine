<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Performance\JWS;

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
use Ntuple\Synctree\Util\Authorization\JWT\GenerateToken;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class JWSCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'jwt-jws-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $key;
    private $header;
    private $payload;

    /**
     * JWSCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $key
     * @param IBlock|null $header
     * @param IBlock|null $payload
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $key = null, IBlock $header = null, IBlock $payload = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->key = $key;
        $this->header = $header;
        $this->payload = $payload;
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
        $this->header = $this->setBlock($this->storage, $data['template']['header']);
        $this->payload = $this->setBlock($this->storage, $data['template']['payload']);

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
                'header' => $this->header->getTemplate(),
                'payload' => $this->payload->getTemplate()
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
            return (new GenerateToken(GenerateToken::JWT_PERFORMANCE_TYPE_JWS, $this->getKey($blockStorage), $this->getHeader($blockStorage), $this->getPayload($blockStorage)))
                ->run();
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-JWS-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return CreateJWK
     * @throws ISynctreeException
     */
    private function getKey(array &$blockStorage): CreateJWK
    {
        $key = $this->key->do($blockStorage);
        if (!$key instanceof CreateJWK) {
            throw (new InvalidArgumentException('Authorization-JWS-Create: Invalid key: Not a JWK type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $key;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getHeader(array &$blockStorage): array
    {
        $header = $this->header->do($blockStorage);
        if (!is_array($header)) {
            throw (new InvalidArgumentException('Authorization-JWS-Create: Invalid header: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $header;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getPayload(array &$blockStorage): array
    {
        $payload = $this->payload->do($blockStorage);
        if (!is_array($payload)) {
            throw (new InvalidArgumentException('Authorization-JWS-Create: Invalid payload: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $payload;
    }
}