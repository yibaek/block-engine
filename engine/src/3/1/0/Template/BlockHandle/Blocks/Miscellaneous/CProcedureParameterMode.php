<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CProcedureParameterMode implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'procedure-parameter-mode';

    public const PROCEDURE_PARAMETER_MODE_IN = 'IN';
    public const PROCEDURE_PARAMETER_MODE_OUT = 'OUT';
    public const PROCEDURE_PARAMETER_MODE_INOUT = 'INOUT';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $parameterMode;

    /**
     * CProcedureParameterMode constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param string|null $parameterMode
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, string $parameterMode = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->parameterMode = $parameterMode;
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
        $this->parameterMode = $data['template']['parameter-mode'];

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
                'parameter-mode' => $this->parameterMode
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     */
    public function do(array &$blockStorage): string
    {
        return $this->parameterMode;
    }
}