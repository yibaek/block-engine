<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Subject;

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

class BearerConfirmation implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-assertion-subject-confirmation-bearer';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $inResponseTo;
    private $notOnOrAfter;
    private $recipient;

    /**
     * BearerConfirmation constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $inResponseto
     * @param IBlock|null $notOnAfter
     * @param IBlock|null $recipient
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $inResponseto = null, IBlock $notOnAfter = null, IBlock $recipient = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->inResponseTo = $inResponseto;
        $this->notOnOrAfter = $notOnAfter;
        $this->recipient = $recipient;
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
        $this->inResponseTo = $this->setBlock($this->storage, $data['template']['in-response-to']);
        $this->notOnOrAfter = $this->setBlock($this->storage, $data['template']['not-on-or-after']);
        $this->recipient = $this->setBlock($this->storage, $data['template']['recipient']);

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
                'in-response-to' => $this->inResponseTo->getTemplate(),
                'not-on-or-after' => $this->notOnOrAfter->getTemplate(),
                'recipient' => $this->recipient->getTemplate()
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
                    'in_response_to' => $this->getInResponseTo($blockStorage),
                    'noton_or_after' => $this->getNotOnOrAfter($blockStorage),
                    'recipient' => $this->getRecipient($blockStorage)
                ]
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Assertion-Subject-Confirmation-Bearer'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getInResponseTo(array &$blockStorage): ?string
    {
        $inResponseTo = $this->inResponseTo->do($blockStorage);
        if (!is_null($inResponseTo)) {
            if (!is_string($inResponseTo)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Subject-Confirmation-Bearer: Invalid inResponseTo: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $inResponseTo;
    }

    /**
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getNotOnOrAfter(array &$blockStorage): ?int
    {
        $notOnOrAfter = $this->notOnOrAfter->do($blockStorage);
        if (!is_null($notOnOrAfter)) {
            if (!is_int($notOnOrAfter)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Subject-Confirmation-Bearer: Invalid notOnOrAfter: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $notOnOrAfter;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getRecipient(array &$blockStorage): ?string
    {
        $recipient = $this->recipient->do($blockStorage);
        if (!is_null($recipient)) {
            if (!is_string($recipient)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Subject-Confirmation-Bearer: Invalid recipient: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $recipient;
    }

    /**
     * @return string
     */
    private function getType(): string
    {
        return 'bearer';
    }
}