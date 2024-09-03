<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Operator;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Context\ProtocolContext;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Template\Storage\RequestOperator as RequestOperatorStorage;
use Ntuple\Synctree\Template\Storage\ResponseOperator as ResponseOperatorStorage;
use Ntuple\Synctree\Template\Storage\RestfulOperator as RestfulOperatorStorage;
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class RestfulOperator implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'operator';
    public const ACTION = 'restful';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $id;
    private $from;
    private $to;
    private $protocol;
    private $contexts;

    /**
     * RestfulOperator constructor.
     * @param PlanStorage $storage
     * @param string|null $id
     * @param ExtraManager|null $extra
     * @param IBlock|null $from
     * @param IBlock|null $to
     * @param IBlock|null $protocol
     * @param BlockAggregator|null $contexts
     */
    public function __construct(PlanStorage $storage, string $id = null, ExtraManager $extra = null, IBlock $from = null, IBlock $to = null, IBlock $protocol = null, BlockAggregator $contexts = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->id = $id;
        $this->from = $from;
        $this->to = $to;
        $this->protocol = $protocol;
        $this->contexts = $contexts;
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
        $this->protocol = $this->setBlock($this->storage, $data['template']['protocol']);
        $this->contexts = $this->setBlocks($this->storage, $data['template']['contexts']);

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
                'protocol' => $this->protocol->getTemplate(),
                'contexts' => $this->getTemplateEachContext()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return void
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): void
    {
        try {
            $reqStorage = new RequestOperatorStorage();
            $resStorage = new ResponseOperatorStorage();

            // call protocol
            [$reqProtocolContext, $resProtocolContext] = $this->protocol->do($blockStorage);

            foreach ($this->contexts as $context) {
                $responseContext = $this->getContext($blockStorage, $context);

                if ($responseContext->getStatusCode() === null) {
                    $reqStorage->setHeader($reqProtocolContext->getHeader());
                    $reqStorage->setBody($reqProtocolContext->getBody());

                    $resStorage->setStatusCode($resProtocolContext->getStatusCode());
                    $resStorage->setHeader($this->getHeader($resProtocolContext->getHeader(), $responseContext->getHeader()));
                    $resStorage->setBody($this->getBody($resProtocolContext->getBody(), $responseContext->getBody()));
                    break;
                }

                if ($resProtocolContext->getStatusCode() === $responseContext->getStatusCode()) {
                    $reqStorage->setHeader($reqProtocolContext->getHeader());
                    $reqStorage->setBody($reqProtocolContext->getBody());

                    $resStorage->setStatusCode($resProtocolContext->getStatusCode());
                    $resStorage->setHeader($this->getHeader($resProtocolContext->getHeader(), $responseContext->getHeader()));
                    $resStorage->setBody($this->getBody($resProtocolContext->getBody(), $responseContext->getBody()));
                    break;
                }
            }

            // set restful operator storage
            $this->storage->addOperatorStorage($this->id, new RestfulOperatorStorage($reqStorage, $resStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('TransferOperator'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachContext(): array
    {
        $resData = [];
        foreach ($this->contexts as $context) {
            $resData[] = $context->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @param mixed $context
     * @return ProtocolContext
     * @throws ISynctreeException
     */
    private function getContext(array &$blockStorage, $context): ProtocolContext
    {
        $data = $context->do($blockStorage);
        if (!$data instanceof ProtocolContext) {
            throw (new InvalidArgumentException('TransferOperator: Invalid context: Not a response context type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $data;
    }

    /**
     * @param array $source
     * @param array $header
     * @return array
     * @throws Exception
     */
    private function getHeader(array $source, array $header): array
    {
        if (empty($header)) {
            return $source;
        }

        $target = [];
        foreach ($header as $key => $value) {
            $target[] = $key;
        }

        return CommonUtil::intersectHeader(CommonUtil::reOrderHeader($source), $target);
    }

    /**
     * @param mixed $source
     * @param mixed $body
     * @return mixed
     * @throws Exception
     */
    private function getBody($source, $body)
    {
        if (empty($body) || !is_array($source)) {
            return $source;
        }

        return CommonUtil::intersectParameter($source, $body);
    }
}