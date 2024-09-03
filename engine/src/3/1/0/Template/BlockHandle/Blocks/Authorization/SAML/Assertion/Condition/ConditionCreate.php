<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Condition;

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

class ConditionCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-assertion-condition-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $notBefore;
    private $notOnOrAfter;
    private $items;

    /**
     * ConditionCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $notBefore
     * @param IBlock|null $notOnOrAfter
     * @param BlockAggregator|null $items
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $notBefore = null, IBlock $notOnOrAfter = null, BlockAggregator $items = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->notBefore = $notBefore;
        $this->notOnOrAfter = $notOnOrAfter;
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
        $this->notBefore = $this->setBlock($this->storage, $data['template']['not-before']);
        $this->notOnOrAfter = $this->setBlock($this->storage, $data['template']['not-on-or-after']);
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
                'not-before' => $this->notBefore->getTemplate(),
                'not-on-or-after' => $this->notOnOrAfter->getTemplate(),
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
                'not_before' => $this->getNotBefore($blockStorage),
                'noton_or_after' => $this->getNotOnOrAfter($blockStorage),
                'items' => $this->getItems($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Assertion-Condition-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
     * @return int|null
     * @throws ISynctreeException
     */
    private function getNotBefore(array &$blockStorage): ?int
    {
        $notBefore = $this->notBefore->do($blockStorage);
        if (!is_null($notBefore)) {
            if (!is_int($notBefore)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Condition-Create: Invalid notBefore: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $notBefore;
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
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Condition-Create: Invalid notOnOrAfter: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $notOnOrAfter;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getItems(array &$blockStorage): array
    {
        $resData = [];
        foreach ($this->items as $item) {
            $data = $item->do($blockStorage);
            if (!is_array($data)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Condition-Create: Invalid item: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
            $resData[] = $data;
        }

        return $resData;
    }
}