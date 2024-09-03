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
use Ntuple\Synctree\Util\Authorization\OAuth2\Authorize as AuthorizeUtil;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class Authorize implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'oauth2-authorize';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $scope;
    private $expiresIn;
    private $userID;
    private $validateOnly;

    /**
     * Authorize constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $scope
     * @param IBlock|null $expiresIn
     * @param IBlock|null $userID
     * @param IBlock|null $validateOnly
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $scope = null, IBlock $expiresIn = null, IBlock $userID = null, IBlock $validateOnly = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->scope = $scope;
        $this->expiresIn = $expiresIn;
        $this->userID = $userID ?? $this->getDefaultBlock();
        $this->validateOnly = $validateOnly ?? $this->getDefaultBlock();
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
        $this->userID = isset($data['template']['userid']) ?$this->setBlock($this->storage, $data['template']['userid']) :$this->getDefaultBlock();
        $this->validateOnly = isset($data['template']['validate-only']) ?$this->setBlock($this->storage, $data['template']['validate-only']) :$this->getDefaultBlock();

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
                'userid' => $this->userID->getTemplate(),
                'validate-only' => $this->validateOnly->getTemplate()
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
            return (new AuthorizeUtil($this->storage))
                ->setSupportedScopes($this->getScope($blockStorage))
                ->setExpiresIn($this->getExpiresIn($blockStorage))
                ->setUserID($this->getUserID($blockStorage))
                ->setValidateOnly($this->getValidateOnly($blockStorage))
                ->run($this->storage->getOrigin()->getHeaders(), $this->storage->getOrigin()->getBodys());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-OAuth2-Authorize'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
                throw (new InvalidArgumentException('Authorization-OAuth2-Authorize: Invalid supported scope: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
                throw (new InvalidArgumentException('Authorization-OAuth2-Authorize: Invalid expiresIn: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $expiresIn;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getUserID(array &$blockStorage): ?string
    {
        $userID = $this->userID->do($blockStorage);
        if (!is_null($userID)) {
            if (!is_string($userID)) {
                throw (new InvalidArgumentException('Authorization-OAuth2-Authorize: Invalid userId: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $userID;
    }

    /**
     * @param array $blockStorage
     * @return bool|null
     * @throws ISynctreeException
     */
    private function getValidateOnly(array &$blockStorage): ?bool
    {
        $validateOnly = $this->validateOnly->do($blockStorage);
        if (!is_null($validateOnly)) {
            if (!is_bool($validateOnly)) {
                throw (new InvalidArgumentException('Authorization-OAuth2-Authorize: Invalid validate only: Not a boolean type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $validateOnly;
    }

    /**
     * @return CNull
     */
    private function getDefaultBlock(): CNull
    {
        return new CNull($this->storage, $this->extra);
    }
}