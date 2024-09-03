<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class AssertionCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-assertion-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $id;
    private $issuer;
    private $destination;
    private $inResponseTo;
    private $subject;
    private $condition;
    private $items;

    /**
     * AssertionCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $id
     * @param IBlock|null $issuer
     * @param IBlock|null $destination
     * @param IBlock|null $inResponseTo
     * @param IBlock|null $subject
     * @param IBlock|null $condition
     * @param BlockAggregator|null $items
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $id = null, IBlock $issuer = null, IBlock $destination = null, IBlock $inResponseTo = null, IBlock $subject = null, IBlock $condition = null, BlockAggregator $items = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->id = $id;
        $this->issuer = $issuer;
        $this->destination = $destination;
        $this->inResponseTo = $inResponseTo;
        $this->subject = $subject;
        $this->condition = $condition;
        $this->items = $items;
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
        $this->id = $this->setBlock($this->storage, $data['template']['id']);
        $this->issuer = $this->setBlock($this->storage, $data['template']['issuer']);
        $this->destination = $this->setBlock($this->storage, $data['template']['destination']);
        $this->inResponseTo = $this->setBlock($this->storage, $data['template']['in-response-to']);
        $this->subject = $this->setBlock($this->storage, $data['template']['subject']);
        $this->condition = $this->setBlock($this->storage, $data['template']['condition']);
        $this->items = $this->setBlocks($this->storage, $data['template']['item']);

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
                'id' => $this->id->getTemplate(),
                'issuer' => $this->issuer->getTemplate(),
                'destination' => $this->destination->getTemplate(),
                'in-response-to' => $this->inResponseTo->getTemplate(),
                'subject' => $this->subject->getTemplate(),
                'condition' => $this->condition->getTemplate(),
                'item' => $this->getTemplateEachItem()
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
                'request_id' => $this->getID($blockStorage),
                'issuer' => $this->getIssuer($blockStorage),
                'destination' => $this->getDestination($blockStorage),
                'in_response_to' => $this->getInResponseTo($blockStorage),
                'subject' => $this->getSubject($blockStorage),
                'condition' => $this->getCondition($blockStorage),
                'items' => $this->getItems($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Assertion-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachItem(): array
    {
        $resData = [];
        foreach ($this->items as $item) {
            $resData[] = $item->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getID(array &$blockStorage): ?string
    {
        $id = $this->id->do($blockStorage);
        if (!is_null($id)) {
            if (!is_string($id)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Create: Invalid id: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $id;
    }

    /**
     * @param array $blockStorage
     * @return string|null|array
     * @throws ISynctreeException
     */
    private function getIssuer(array &$blockStorage)
    {
        $issuer = $this->issuer->do($blockStorage);
        if (!is_null($issuer)) {
            if (!is_string($issuer) && !is_array($issuer)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Create: Invalid issuer: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $issuer;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getDestination(array &$blockStorage): ?string
    {
        $destination = $this->destination->do($blockStorage);
        if (!is_null($destination)) {
            if (!is_string($destination)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Create: Invalid destination: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $destination;
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
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Create: Invalid inResponseTo: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $inResponseTo;
    }

    /**
     * @param $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getSubject(&$blockStorage): ?array
    {
        $subject = $this->subject->do($blockStorage);
        if (!is_null($subject)) {
            if (!is_array($subject)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Create: Invalid subject: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $subject;
    }

    /**
     * @param $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getCondition(&$blockStorage): ?array
    {
        $condition = $this->condition->do($blockStorage);
        if (!is_null($condition)) {
            if (!is_array($condition)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Create: Invalid condition: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $condition;
    }

    /**
     * @param array $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getItems(array &$blockStorage): ?array
    {
        $resData = [];
        foreach ($this->items as $item) {
            $data = $item->do($blockStorage);
            if (!is_array($data)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Create: Invalid item: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
            $resData[] = $data;
        }

        return $resData;
    }
}