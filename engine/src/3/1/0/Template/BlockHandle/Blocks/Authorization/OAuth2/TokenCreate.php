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
use Ntuple\Synctree\Util\Authorization\OAuth2\GenerateToken;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class TokenCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'oauth2-token-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $scope;
    private $expiresIn;
    private $tokenType;
    private $refreshExpiresIn;
    private $newRefreshToken;
    private $extension;

    /**
     * TokenCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $scope
     * @param IBlock|null $expiresIn
     * @param IBlock|null $tokenType
     * @param IBlock|null $refreshExpiresIn
     * @param IBlock|null $newRefreshToken
     * @param IBlock|null $extension
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $scope = null, IBlock $expiresIn = null, IBlock $tokenType = null, IBlock $refreshExpiresIn = null, IBlock $newRefreshToken = null, IBlock $extension = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->scope = $scope;
        $this->expiresIn = $expiresIn;
        $this->tokenType = $tokenType ?? $this->getDefaultBlock();
        $this->refreshExpiresIn = $refreshExpiresIn ?? $this->getDefaultBlock();
        $this->newRefreshToken = $newRefreshToken ?? $this->getDefaultBlock();
        $this->extension = $extension ?? $this->getDefaultBlock();
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
        $this->scope = $this->setBlock($this->storage, $data['template']['scope']);
        $this->expiresIn = $this->setBlock($this->storage, $data['template']['expires-in']);
        $this->tokenType = isset($data['template']['token-type']) ?$this->setBlock($this->storage, $data['template']['token-type']) :$this->getDefaultBlock();
        $this->refreshExpiresIn = isset($data['template']['refresh-expires-in']) ?$this->setBlock($this->storage, $data['template']['refresh-expires-in']) :$this->getDefaultBlock();
        $this->newRefreshToken = isset($data['template']['new-refresh-token']) ?$this->setBlock($this->storage, $data['template']['new-refresh-token']) :$this->getDefaultBlock();
        $this->extension = isset($data['template']['extension']) ?$this->setBlock($this->storage, $data['template']['extension']) :$this->getDefaultBlock();

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
                'scope' => $this->scope->getTemplate(),
                'expires-in' => $this->expiresIn->getTemplate(),
                'token-type' => $this->tokenType->getTemplate(),
                'refresh-expires-in' => $this->refreshExpiresIn->getTemplate(),
                'new-refresh-token' => $this->newRefreshToken->getTemplate(),
                'extension' => $this->extension->getTemplate()
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
            return (new GenerateToken($this->storage))
                ->setSupportedScopes($this->getScope($blockStorage))
                ->setExpiresIn($this->getExpiresIn($blockStorage))
                ->setTokenType($this->getTokenType($blockStorage))
                ->setRefreshTokenLifeTime($this->getRefreshExpiresIn($blockStorage))
                ->setAlwaysIssueNewRefreshToken($this->getNewRefreshToken($blockStorage))
                ->setExtension($this->getExtension($blockStorage))
                ->run($this->storage->getOrigin()->getHeaders(), $this->storage->getOrigin()->getBodys());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-OAuth2-Token-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
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
                throw (new InvalidArgumentException('Authorization-OAuth2-Token-Create: Invalid supported scope: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $scope;
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getExpiresIn(array &$blockStorage): ?int
    {
        $expiresIn = $this->expiresIn->do($blockStorage);
        if (!is_null($expiresIn)) {
            if (!is_int($expiresIn)) {
                throw (new InvalidArgumentException('Authorization-OAuth2-Token-Create: Invalid expiresIn: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $expiresIn;
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
                throw (new InvalidArgumentException('Authorization-OAuth2-Token-Create: Invalid token type: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $tokenType;
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getRefreshExpiresIn(array &$blockStorage): ?int
    {
        $refreshExpiresIn = $this->refreshExpiresIn->do($blockStorage);
        if (!is_null($refreshExpiresIn)) {
            if (!is_int($refreshExpiresIn)) {
                throw (new InvalidArgumentException('Authorization-OAuth2-Token-Create: Invalid refresh expiresIn: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $refreshExpiresIn;
    }

    /**
     * @param array $blockStorage
     * @return bool|null
     * @throws ISynctreeException
     */
    private function getNewRefreshToken(array &$blockStorage): ?bool
    {
        $newRefreshToken = $this->newRefreshToken->do($blockStorage);
        if (!is_null($newRefreshToken)) {
            if (!is_bool($newRefreshToken)) {
                throw (new InvalidArgumentException('Authorization-OAuth2-Token-Create: Invalid new refresh token: Not a boolean type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $newRefreshToken;
    }

    /**
     * @param array $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getExtension(array &$blockStorage): ?array
    {
        $extension = $this->extension->do($blockStorage);
        if (!is_null($extension)) {
            if (!is_array($extension)) {
                throw (new InvalidArgumentException('Authorization-OAuth2-Token-Create: Invalid extension: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $extension;
    }

    /**
     * @return CNull
     */
    private function getDefaultBlock(): CNull
    {
        return new CNull($this->storage, $this->extra);
    }
}