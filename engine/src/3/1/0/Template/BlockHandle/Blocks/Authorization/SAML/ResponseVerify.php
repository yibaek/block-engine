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
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Authorization\SAML\ValidateResponse;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class ResponseVerify implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-response-verify';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $assertion;
    private $useBase64;
    private $signature;

    /**
     * ResponseVerify constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $assertion
     * @param IBlock|null $useBase64
     * @param IBlock|null $signature
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $assertion = null, IBlock $useBase64 = null, IBlock $signature = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->assertion = $assertion;
        $this->useBase64 = $useBase64;
        $this->signature = $signature;
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
                'signature' => $this->signature->getTemplate()
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
            return (new ValidateResponse($this->storage))
                ->setUseBase64($this->getUseBase64($blockStorage))
                ->setSignature($this->getSignature($blockStorage))
                ->setAssertion($this->getAssertion($blockStorage))
                ->run($this->storage->getOrigin()->getHeaders(), $this->storage->getOrigin()->getBodys());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Response-Verify'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getAssertion(array &$blockStorage): ?string
    {
        $assertion = $this->assertion->do($blockStorage);
        if (!is_null($assertion)) {
            if (!is_string($assertion)) {
                throw (new InvalidArgumentException('Authorization-SAML-Response-Verify: Invalid assertion: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
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
                throw (new InvalidArgumentException('Authorization-SAML-Response-Verify: Invalid useBase64: Not a boolean type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
                throw (new InvalidArgumentException('Authorization-SAML-Response-Verify: Invalid signature: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $signature;
    }
}