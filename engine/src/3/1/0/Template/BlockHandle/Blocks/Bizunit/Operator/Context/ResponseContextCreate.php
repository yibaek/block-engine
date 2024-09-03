<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Operator\Context;

use Exception;
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

class ResponseContextCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'response-context';
    public const ACTION = 'create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $statusCode;
    private $header;
    private $body;

    /**
     * ResponseContextCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $statusCode
     * @param IBlock|null $header
     * @param IBlock|null $body
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $statusCode = null, IBlock $header = null, IBlock $body = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->statusCode = $statusCode;
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
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->statusCode = $this->setBlock($this->storage, $data['template']['status_code']);
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
            'extra' => $this->extra->getData(),
            'template' => [
                'status_code' => $this->statusCode->getTemplate(),
                'header' => $this->header->getTemplate(),
                'body' => $this->body->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return ProtocolContext
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): ProtocolContext
    {
        try {
            return new ProtocolContext($this->getHeader($blockStorage), $this->getBody($blockStorage), $this->getStatusCode($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('ResponseContext'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('ResponseContext: Invalid header: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
     * @param array $blockStorage
     * @return int|null
     * @throws ISynctreeException
     */
    private function getStatusCode(array &$blockStorage): ?int
    {
        $statusCode = $this->statusCode->do($blockStorage);
        if (!is_null($statusCode)) {
            if (!is_int($statusCode)) {
                throw (new InvalidArgumentException('ResponseContext: Invalid status code: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $statusCode;
    }
}