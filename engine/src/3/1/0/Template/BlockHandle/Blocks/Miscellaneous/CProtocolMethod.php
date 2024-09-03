<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CProtocolMethod implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'protocol-method';

    public const PROTOCOL_METHOD_POST = 'post';
    public const PROTOCOL_METHOD_GET = 'get';
    public const PROTOCOL_METHOD_PUT = 'put';
    public const PROTOCOL_METHOD_DELETE = 'delete';
    public const PROTOCOL_METHOD_PATCH = 'patch';
    public const PROTOCOL_METHOD_SECURE = 'secure';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $method;

    /**
     * CProtocolMethod constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param string|null $method
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, string $method = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->method = $method;
    }

    /**
     * @param array $data
     * @return IBlock
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->method = $data['template']['method'];

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
                'method' => $this->method
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     */
    public function do(array &$blockStorage): string
    {
        return $this->method;
    }
}