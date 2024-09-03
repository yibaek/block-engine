<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Items;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive\CNull;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class AuthnStatement implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-assertion-item-authn-statement';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $instant;
    private $sessionIndex;
    private $sessionNotOnOrAfter;
    private $context;
    private $subjectLocality;

    /**
     * AuthnStatement constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $instant
     * @param IBlock|null $sessionIndex
     * @param IBlock|null $sessionNotOnOrAfter
     * @param IBlock|null $context
     * @param IBlock|null $subjectLocality
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $instant = null, IBlock $sessionIndex = null, IBlock $sessionNotOnOrAfter = null, IBlock $context = null, IBlock $subjectLocality = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->instant = $instant;
        $this->sessionIndex = $sessionIndex;
        $this->sessionNotOnOrAfter = $sessionNotOnOrAfter;
        $this->context = $context;
        $this->subjectLocality = $subjectLocality ?? $this->getDefaultBlock();
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
        $this->instant = $this->setBlock($this->storage, $data['template']['instant']);
        $this->sessionIndex = $this->setBlock($this->storage, $data['template']['session-index']);
        $this->sessionNotOnOrAfter = $this->setBlock($this->storage, $data['template']['session-not-on-or-after']);
        $this->context = $this->setBlock($this->storage, $data['template']['context']);
        $this->subjectLocality = isset($data['template']['subject-locality']) ?$this->setBlock($this->storage, $data['template']['subject-locality']) :$this->getDefaultBlock();

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
                'instant' => $this->instant->getTemplate(),
                'session-index' => $this->sessionIndex->getTemplate(),
                'session-not-on-or-after' => $this->sessionNotOnOrAfter->getTemplate(),
                'context' => $this->context->getTemplate(),
                'subject-locality' => $this->subjectLocality->getTemplate()
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
                'type' => $this->getType(),
                'value' => [
                    'instant' => $this->getInstant($blockStorage),
                    'session_index' => $this->getSessionIndex($blockStorage),
                    'session_noton_or_after' => $this->getSessionNotOnOrAfter($blockStorage),
                    'context' => $this->getContext($blockStorage),
                    'subject_locality' => $this->getSubjectLocality($blockStorage)
                ]
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Assertion-Item-AuthnStatement'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getInstant(array &$blockStorage): ?int
    {
        $instant = $this->instant->do($blockStorage);
        if (!is_null($instant)) {
            if (!is_int($instant)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Item-AuthnStatement: Invalid instant: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $instant;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getSessionIndex(array &$blockStorage): ?string
    {
        $sessionIndex = $this->sessionIndex->do($blockStorage);
        if (!is_null($sessionIndex)) {
            if (!is_string($sessionIndex)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Item-AuthnStatement: Invalid sessionIndex: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $sessionIndex;
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getSessionNotOnOrAfter(array &$blockStorage): ?int
    {
        $sessionNotOnOrAfter = $this->sessionNotOnOrAfter->do($blockStorage);
        if (!is_null($sessionNotOnOrAfter)) {
            if (!is_int($sessionNotOnOrAfter)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Item-AuthnStatement: Invalid sessionNotOnOrAfter: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $sessionNotOnOrAfter;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getContext(array &$blockStorage): ?string
    {
        $context = $this->context->do($blockStorage);
        if (!is_null($context)) {
            if (!is_string($context)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Item-AuthnStatement: Invalid context: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $context;
    }

    /**
     * @param array $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getSubjectLocality(array &$blockStorage): ?array
    {
        $subjectLocality = $this->subjectLocality->do($blockStorage);
        if (!is_null($subjectLocality)) {
            if (!is_array($subjectLocality)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Item-AuthnStatement: Invalid subjectLocality: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $subjectLocality;
    }

    /**
     * @return CNull
     */
    private function getDefaultBlock(): CNull
    {
        return new CNull($this->storage, $this->extra);
    }

    /**
     * @return string
     */
    private function getType(): string
    {
        return 'authn_statement';
    }
}