<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Operator;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Template\Storage\RequestOperator as RequestOperatorStorage;
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class RequestOperator implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'operator';
    public const ACTION = 'request';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $id;
    private $from;
    private $to;
    private $method;
    private $endPoint;
    private $header;
    private $body;

    /**
     * RequestOperator constructor.
     * @param PlanStorage $storage
     * @param string|null $id
     * @param ExtraManager|null $extra
     * @param IBlock|null $from
     * @param IBlock|null $to
     * @param IBlock|null $method
     * @param IBlock|null $endPoint
     * @param IBlock|null $header
     * @param IBlock|null $body
     */
    public function __construct(PlanStorage $storage, string $id = null, ExtraManager $extra = null, IBlock $from = null, IBlock $to = null, IBlock $method = null, IBlock $endPoint = null, IBlock $header = null, IBlock $body = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->id = $id;
        $this->from = $from;
        $this->to = $to;
        $this->method = $method;
        $this->endPoint = $endPoint;
        $this->header = $header;
        $this->body = $body;
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
        $this->method = $this->setBlock($this->storage, $data['template']['method']);
        $this->endPoint = $this->setBlock($this->storage, $data['template']['end-point']);
        $this->header = $this->setBlock($this->storage, $data['template']['header']);
        $this->body = $this->setBlock($this->storage, $data['template']['body']);

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
                'method' => $this->method->getTemplate(),
                'end-point' => $this->endPoint->getTemplate(),
                'header' => $this->header->getTemplate(),
                'body' => $this->body->getTemplate()
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
            $header = $this->makeHeader($this->storage->getOrigin()->getHeaders(), $this->getHeader($blockStorage));
            $body = $this->makeBody($this->storage->getOrigin()->getBodys(), $this->getBody($blockStorage));

            $this->storage->addOperatorStorage($this->id, new RequestOperatorStorage($header, $body));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('RequestOperator'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getHeader(array &$blockStorage): array
    {
        $header = $this->header->do($blockStorage);
        if (!is_array($header)) {
            throw (new InvalidArgumentException('RequestOperator: Invalid header: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $header;
    }

    /**
     * @param array $blockStorage
     * @return mixed
     */
    private function getBody(array &$blockStorage)
    {
        return $this->body->do($blockStorage);
    }

    /**
     * @param array $source
     * @param array $header
     * @return array
     * @throws Exception
     */
    private function makeHeader(array $source, array $header): array
    {
        if (empty($header)) {
            return $source;
        }

        $target = [];
        foreach ($header as $key => $value) {
            $target[] = $key;
        }

        return CommonUtil::intersectHeader($source, $target);
    }

    /**
     * @param array $source
     * @param mixed $body
     * @return mixed
     * @throws Exception
     */
    private function makeBody($source, $body)
    {
        if (!is_array($source) || empty($body)) {
            return $source;
        }

        return CommonUtil::intersectParameter($source, $body);
    }
}