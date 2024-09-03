<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML;

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
use Ntuple\Synctree\Util\Authorization\SAML\GenerateResponse;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class ResponseCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-response-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $assertion;
    private $useBase64;
    private $signature;
    private $useOauthBearerAssertion;

    /**
     * ResponseCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $assertion
     * @param IBlock|null $useBase64
     * @param IBlock|null $signature
     * @param IBlock|null $useOauthBearerAssertion
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $assertion = null, IBlock $useBase64 = null, IBlock $signature = null, IBlock $useOauthBearerAssertion = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->assertion = $assertion;
        $this->useBase64 = $useBase64;
        $this->signature = $signature;
        $this->useOauthBearerAssertion = $useOauthBearerAssertion ?? $this->getDefaultBlock();
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
        $this->assertion = $this->setBlock($this->storage, $data['template']['assertion']);
        $this->useBase64 = $this->setBlock($this->storage, $data['template']['use-base64']);
        $this->signature = $this->setBlock($this->storage, $data['template']['signature']);
        $this->useOauthBearerAssertion = isset($data['template']['use-oauth-bearer-assertion']) ?$this->setBlock($this->storage, $data['template']['use-oauth-bearer-assertion']) :$this->getDefaultBlock();

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
                'assertion' => $this->assertion->getTemplate(),
                'use-base64' => $this->useBase64->getTemplate(),
                'signature' => $this->signature->getTemplate(),
                'use-oauth-bearer-assertion' => $this->useOauthBearerAssertion->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array|string
     * @throws GuzzleException
     * @throws ISynctreeException
     * @throws Throwable
     */
    public function do(array &$blockStorage)
    {
        try {
            return (new GenerateResponse($this->storage))
                ->setUseBase64($this->getUseBase64($blockStorage))
                ->setSignature($this->getSignature($blockStorage))
                ->setUseOauthBearerAssertion($this->getUseOauthBearerAssertion($blockStorage))
                ->setAssertion($this->getAssertion($blockStorage))
                ->run($this->storage->getOrigin()->getHeaders(), $this->storage->getOrigin()->getBodys());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Response-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getAssertion(array &$blockStorage): array
    {
        $assertion = $this->assertion->do($blockStorage);
        if (!is_array($assertion)) {
            throw (new InvalidArgumentException('Authorization-SAML-Response-Create: Invalid assertion: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $assertion;
    }

    /**
     * @param array $blockStorage
     * @return bool|null
     * @throws ISynctreeException
     */
    private function getUseBase64(array &$blockStorage): ?bool
    {
        $useBase64 = $this->useBase64->do($blockStorage);
        if (!is_null($useBase64)) {
            if (!is_bool($useBase64)) {
                throw (new InvalidArgumentException('Authorization-SAML-Response-Create: Invalid useBase64: Not a boolean type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $useBase64;
    }

    /**
     * @param array $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getSignature(array &$blockStorage): ?array
    {
        $signature = $this->signature->do($blockStorage);
        if (!is_null($signature)) {
            if (!is_array($signature)) {
                throw (new InvalidArgumentException('Authorization-SAML-Response-Create: Invalid signature: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $signature;
    }

    /**
     * @param array $blockStorage
     * @return bool|null
     * @throws ISynctreeException
     */
    private function getUseOauthBearerAssertion(array &$blockStorage): ?bool
    {
        $useOauthBearerAssertion = $this->useOauthBearerAssertion->do($blockStorage);
        if (!is_null($useOauthBearerAssertion)) {
            if (!is_bool($useOauthBearerAssertion)) {
                throw (new InvalidArgumentException('Authorization-SAML-Response-Create: Invalid useOauthBearerAssertion: Not a boolean type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $useOauthBearerAssertion;
    }

    /**
     * @return CNull
     */
    private function getDefaultBlock(): CNull
    {
        return new CNull($this->storage, $this->extra);
    }
}