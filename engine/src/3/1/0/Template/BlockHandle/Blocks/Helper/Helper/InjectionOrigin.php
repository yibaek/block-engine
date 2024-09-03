<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Helper\Helper;

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

class InjectionOrigin implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'helper';
    public const ACTION = 'injection-origin';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $header;
    private $body;

    /**
     * InjectionOrigin constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $header
     * @param IBlock|null $body
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $header = null, IBlock $body = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
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
            $originStorage = $this->storage->getOrigin();

            if ($header=$this->getHeader($blockStorage)) {
                $originStorage->setHeader($header, true);
            }

            if ($body=$this->getBody($blockStorage)) {
                $originStorage->setBody($body, true);
            }
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Helper-Injection-Origin'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getHeader(array &$blockStorage): ?array
    {
        $header = $this->header->do($blockStorage);
        if (!is_null($header)) {
            if (!is_array($header)) {
                throw (new InvalidArgumentException('Helper-Injection-Origin: Invalid header: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $header;
    }

    /**
     * @param array $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getBody(array &$blockStorage): ?array
    {
        $body = $this->body->do($blockStorage);
        if (!is_null($body)) {
            if (!is_array($body)) {
                throw (new InvalidArgumentException('Helper-Injection-Origin: Invalid body: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $body;
    }
}