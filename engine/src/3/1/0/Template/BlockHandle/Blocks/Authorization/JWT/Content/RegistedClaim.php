<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Content;

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

class RegistedClaim implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'jwt-content-registed-claim';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $issuer;
    private $subject;
    private $audience;
    private $expiraton;
    private $notBefore;
    private $jwtID;

    /**
     * RegisteredClaim constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $issuer
     * @param IBlock|null $subject
     * @param IBlock|null $audience
     * @param IBlock|null $expiraton
     * @param IBlock|null $notBefore
     * @param IBlock|null $jwtID
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $issuer = null, IBlock $subject = null, IBlock $audience = null, IBlock $expiraton = null, IBlock $notBefore = null, IBlock $jwtID = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->issuer = $issuer;
        $this->subject = $subject;
        $this->audience = $audience;
        $this->expiraton = $expiraton;
        $this->notBefore = $notBefore;
        $this->jwtID = $jwtID;
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
        $this->issuer = $this->setBlock($this->storage, $data['template']['issuer']);
        $this->subject = $this->setBlock($this->storage, $data['template']['subject']);
        $this->audience = $this->setBlock($this->storage, $data['template']['audience']);
        $this->expiraton = $this->setBlock($this->storage, $data['template']['expiraton']);
        $this->notBefore = $this->setBlock($this->storage, $data['template']['not-before']);
        $this->jwtID = $this->setBlock($this->storage, $data['template']['jwtid']);

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
                'issuer' => $this->issuer->getTemplate(),
                'subject' => $this->subject->getTemplate(),
                'audience' => $this->audience->getTemplate(),
                'expiraton' => $this->expiraton->getTemplate(),
                'not-before' => $this->notBefore->getTemplate(),
                'jwtid' => $this->jwtID->getTemplate()
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
                'iss' => $this->getIssuer($blockStorage),
                'sub' => $this->getSubject($blockStorage),
                'aud' => $this->getAudience($blockStorage),
                'exp' => $this->getExpiraton($blockStorage),
                'nbf' => $this->getNotBefore($blockStorage),
                'jti' => $this->getJwtID($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-JWT-Content-RegistedClaim'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getIssuer(array &$blockStorage): ?string
    {
        $issuer = $this->issuer->do($blockStorage);
        if (!is_null($issuer)) {
            if (!is_string($issuer)) {
                throw (new InvalidArgumentException('Authorization-JWT-Content-RegistedClaim: Invalid issuer: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $issuer;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getSubject(array &$blockStorage): ?string
    {
        $subject = $this->subject->do($blockStorage);
        if (!is_null($subject)) {
            if (!is_string($subject)) {
                throw (new InvalidArgumentException('Authorization-JWT-Content-RegistedClaim: Invalid subject: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $subject;
    }

    /**
     * @param array $blockStorage
     * @return array|string|null
     * @throws ISynctreeException
     */
    private function getAudience(array &$blockStorage)
    {
        $audience = $this->audience->do($blockStorage);
        if (!is_null($audience)) {
            if (!is_array($audience) && !is_string($audience)) {
                throw (new InvalidArgumentException('Authorization-JWT-Content-RegistedClaim: Invalid audience: Not a array or string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $audience;
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getExpiraton(array &$blockStorage): ?int
    {
        $expiraton = $this->expiraton->do($blockStorage);
        if (!is_null($expiraton)) {
            if (!is_int($expiraton)) {
                throw (new InvalidArgumentException('Authorization-JWT-Content-RegistedClaim: Invalid expiraton: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $expiraton;
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getNotBefore(array &$blockStorage): ?int
    {
        $notBefore = $this->notBefore->do($blockStorage);
        if (!is_null($notBefore)) {
            if (!is_int($notBefore)) {
                throw (new InvalidArgumentException('Authorization-JWT-Content-RegistedClaim: Invalid not-before: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $notBefore;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getJwtID(array &$blockStorage): ?string
    {
        $jwtID = $this->jwtID->do($blockStorage);
        if (!is_null($jwtID)) {
            if (!is_string($jwtID)) {
                throw (new InvalidArgumentException('Authorization-JWT-Content-RegistedClaim: Invalid jwtid: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $jwtID;
    }
}