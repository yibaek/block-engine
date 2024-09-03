<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\OAuth2\TokenType\JWT;

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

class JWTTokenCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'oauth2-tokentype-jwt-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $key;
    private $algo;
    private $payload;

    /**
     * JWTTokenCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $key
     * @param IBlock|null $algo
     * @param IBlock|null $payload
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $key = null, IBlock $algo = null, IBlock $payload = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->key = $key;
        $this->algo = $algo;
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
        $this->algo = $this->setBlock($this->storage, $data['template']['algo']);
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
                'algo' => $this->algo->getTemplate(),
                'payload' => $this->payload->getTemplate()
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
                'use_jwt_access_tokens' => true,
                'jwt' => array_merge($this->getHeader($blockStorage), $this->getPayload($blockStorage))
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-OAuth2-TokenType-JWT-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getKey(array &$blockStorage): ?string
    {
        $key = $this->key->do($blockStorage);
        if (!is_null($key)) {
            if (!is_string($key)) {
                throw (new InvalidArgumentException('Authorization-OAuth2-TokenType-JWT-Create: Invalid key: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $key;
   }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getAlog(array &$blockStorage): ?string
    {
        $algo = $this->algo->do($blockStorage);
        if (!is_null($algo)) {
            if (!is_string($algo)) {
                throw (new InvalidArgumentException('Authorization-OAuth2-TokenType-JWT-Create: Invalid algo: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $algo;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getPayload(array &$blockStorage): array
    {
        $payload = $this->payload->do($blockStorage);
        if (is_null($payload)) {
            return [];
        }

        if (!is_array($payload)) {
            throw (new InvalidArgumentException('Authorization-OAuth2-TokenType-JWT-Create: Invalid payload: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $payload;
    }

    /**
     * @param array $blockStorage
     * @return array|array[]
     * @throws ISynctreeException
     */
    private function getHeader(array &$blockStorage): array
    {
        $key = $this->getKey($blockStorage);
        $algo = $this->getAlog($blockStorage);

        if ($key === null || $algo === null) {
            return [];
        }

        return [
            'header' => [
                'key' => $key,
                'algo' => $algo
            ]
        ];
    }
}