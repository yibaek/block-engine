<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Operator;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\BizunitResponseException;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Context\ProtocolContext;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class ResponseOperator implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'operator';
    public const ACTION = 'response';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $id;
    private $from;
    private $to;
    private $context;

    /**
     * ResponseOperator constructor.
     * @param PlanStorage $storage
     * @param string|null $id
     * @param ExtraManager|null $extra
     * @param IBlock|null $from
     * @param IBlock|null $to
     * @param IBlock|null $context
     */
    public function __construct(PlanStorage $storage, string $id = null, ExtraManager $extra = null, IBlock $from = null, IBlock $to = null, IBlock $context = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->id = $id;
        $this->from = $from;
        $this->to = $to;
        $this->context = $context;
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
        $this->id = $data['id'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->from = $this->setBlock($this->storage, $data['template']['from']);
        $this->to = $this->setBlock($this->storage, $data['template']['to']);
        $this->context = $this->setBlock($this->storage, $data['template']['context']);

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
            'id' => $this->id,
            'extra' => $this->extra->getData(),
            'template' => [
                'from' => $this->from->getTemplate(),
                'to' => $this->to->getTemplate(),
                'context' => $this->context->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @throws Throwable|BizunitResponseException|ISynctreeException
     */
    public function do(array &$blockStorage): void
    {
        try {
            $context = $this->getContext($blockStorage);
            throw new BizunitResponseException($context->getStatusCode(), $context->getHeader(), $context->getBody());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('ResponseOperator'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return ProtocolContext
     * @throws ISynctreeException
     */
    private function getContext(array &$blockStorage): ProtocolContext
    {
        $context = $this->context->do($blockStorage);
        if (!$context instanceof ProtocolContext) {
            throw (new InvalidArgumentException('ResponseOperator: Invalid context: Not a response context type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $context;
    }
}