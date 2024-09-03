<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CParameterType implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'parameter-type';

    public const PARAMETER_TYPE_STRING = 'string';
    public const PARAMETER_TYPE_INTEGER = 'integer';
    public const PARAMETER_TYPE_DOUBLE = 'double';
    public const PARAMETER_TYPE_BOOLEAN = 'boolean';
    public const PARAMETER_TYPE_OBJECT = 'object';
    public const PARAMETER_TYPE_ARRAY = 'array';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $parameterType;

    /**
     * CParameterType constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param string|null $parameterType
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, string $parameterType = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->parameterType = $parameterType;
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
        $this->parameterType = $data['template']['parameter-type'];

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
                'parameter-type' => $this->parameterType
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     */
    public function do(array &$blockStorage): string
    {
        return $this->parameterType;
    }
}