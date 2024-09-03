<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\OAuth2;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive\CNull;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Authorization\OAuth2\ValidateToken;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class TokenVerify implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'oauth2-token-verify';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $token;
    private $tokenType;
    private $scope;

    /**
     * TokenVerify constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $token
     * @param IBlock|null $tokenType
     * @param IBlock|null $scope
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $token = null, IBlock $tokenType = null, IBlock $scope = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->token = $token;
        $this->tokenType = $tokenType ?? $this->getDefaultBlock();
        $this->scope = $scope ?? $this->getDefaultBlock();
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
        $this->tokenType = isset($data['template']['token-type']) ?$this->setBlock($this->storage, $data['template']['token-type']) :$this->getDefaultBlock();
        $this->scope = isset($data['template']['scope']) ?$this->setBlock($this->storage, $data['template']['scope']) :$this->getDefaultBlock();

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
                'token-type' => $this->tokenType->getTemplate(),
                'scope' => $this->scope->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws GuzzleException
     * @throws ISynctreeException
     * @throws Throwable
     */
    public function do(array &$blockStorage): array
    {
        try {
            return (new ValidateToken($this->storage))
                ->setToken($this->getToken($blockStorage))
                ->setTokenType($this->getTokenType($blockStorage))
                ->setSupportedScopes($this->getScope($blockStorage))
                ->run($this->storage->getOrigin()->getHeaders(), $this->storage->getOrigin()->getBodys());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-OAuth2-Token-Verify'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getToken(array &$blockStorage): ?string
    {
        $token = $this->token->do($blockStorage);
        if (!is_null($token)) {
            if (!is_string($token)) {
                throw (new InvalidArgumentException('Authorization-OAuth2-Token-Verify: Invalid token: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $token;
    }

    /**
     * @param array $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getTokenType(array &$blockStorage): ?array
    {
        $tokenType = $this->tokenType->do($blockStorage);
        if (!is_null($tokenType)) {
            if (!is_array($tokenType)) {
                throw (new InvalidArgumentException('Authorization-OAuth2-Token-Verify: Invalid token type: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $tokenType;
    }

    /**
     * @param array $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getScope(array &$blockStorage): ?array
    {
        $scope = $this->scope->do($blockStorage);
        if (!is_null($scope)) {
            if (!is_array($scope)) {
                throw (new InvalidArgumentException('Authorization-OAuth2-Token-Verify: Invalid supported scope: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $scope;
    }

    /**
     * @return CNull
     */
    private function getDefaultBlock(): CNull
    {
        return new CNull($this->storage, $this->extra);
    }
}