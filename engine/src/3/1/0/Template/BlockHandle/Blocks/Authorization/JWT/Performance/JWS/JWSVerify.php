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
use Ntuple\Synctree\Util\Authorization\JWT\ValidateToken;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class JWSVerify implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'jwt-jws-verify';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $token;
    private $key;
    private $header;
    private $registedClaim;

    /**
     * JWSVerify constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $token
     * @param IBlock|null $key
     * @param IBlock|null $header
     * @param IBlock|null $registedClaim
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $token = null, IBlock $key = null, IBlock $header = null, IBlock $registedClaim = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->token = $token;
        $this->key = $key;
        $this->header = $header;
        $this->registedClaim = $registedClaim;
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
        $this->token = $this->setBlock($this->storage, $data['template']['token']);
        $this->key = $this->setBlock($this->storage, $data['template']['key']);
        $this->header = $this->setBlock($this->storage, $data['template']['header']);
        $this->registedClaim = $this->setBlock($this->storage, $data['template']['registed-claim']);

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
                'token' => $this->token->getTemplate(),
                'key' => $this->key->getTemplate(),
                'header' => $this->header->getTemplate(),
                'registed-claim' => $this->registedClaim->getTemplate()
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
            return (new ValidateToken(GenerateToken::JWT_PERFORMANCE_TYPE_JWS, $this->getKey($blockStorage), $this->getHeader($blockStorage), $this->getRegistedClaim($blockStorage)))
                ->run($this->getToken($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-JWS-Verify'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Authorization-JWS-Verify: Invalid key: Not a JWK type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Authorization-JWS-Verify: Invalid header: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $header;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getRegistedClaim(array &$blockStorage): array
    {
        $registedClaim = $this->registedClaim->do($blockStorage);
        if (!is_array($registedClaim)) {
            throw (new InvalidArgumentException('Authorization-JWS-Verify: Invalid payload: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $registedClaim;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getToken(array &$blockStorage): string
    {
        $token = $this->token->do($blockStorage);
        if (!is_string($token)) {
            throw (new InvalidArgumentException('Authorization-JWS-Verify: Invalid token: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $token;
    }
}