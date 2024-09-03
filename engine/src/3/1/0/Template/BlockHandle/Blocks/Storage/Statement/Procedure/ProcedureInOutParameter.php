<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Procedure;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CProcedureParameterMode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive\CNull;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class ProcedureInOutParameter implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'statement-procedure-inout-parameter';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $name;
    private $value;
    private $parameterType;
    private $length;

    /**
     * ProcedureInOutParameter constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $value
     * @param IBlock|null $parameterType
     * @param IBlock|null $name
     * @param IBlock|null $length
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $value = null, IBlock $parameterType = null, IBlock $name = null, IBlock $length = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->value = $value;
        $this->parameterType = $parameterType;
        $this->name = $name;
        $this->length = $length ?? $this->getDefaultLength();
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
        $this->value = $this->setBlock($this->storage, $data['template']['value']);
        $this->parameterType = $this->setBlock($this->storage, $data['template']['parameter-type']);
        $this->name = $this->setBlock($this->storage, $data['template']['name']);
        $this->length = $this->setBlock($this->storage, $data['template']['length'] ?? $this->getDefaultLength()->getTemplate());

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
                'value' => $this->value->getTemplate(),
                'parameter-type' => $this->parameterType->getTemplate(),
                'name' => $this->name->getTemplate(),
                'length' => $this->length->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     * @throws Throwable
     */
    public function do(array &$blockStorage): array
    {
        try {
            return [
                $this->getParameterMode(),
                $this->getValue($blockStorage),
                $this->getParameterType($blockStorage),
                $this->getName($blockStorage),
                $this->getLength($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Procedure-Inout-Parameter'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return mixed
     */
    private function getName(array &$blockStorage)
    {
        return $this->name->do($blockStorage);
    }

    /**
     * @param array $blockStorage
     * @return mixed
     */
    private function getValue(array &$blockStorage)
    {
        return $this->value->do($blockStorage);
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getParameterType(array &$blockStorage): string
    {
        $parameterType = $this->parameterType->do($blockStorage);
        if (!is_string($parameterType)) {
            throw (new InvalidArgumentException('Storage-Procedure-Inout-Parameter: Invalid parameter type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $parameterType;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getLength(array &$blockStorage): ?string
    {
        $length = $this->length->do($blockStorage);
        if (!is_null($length)) {
            if (!is_int($length)) {
                throw (new InvalidArgumentException('Storage-Procedure-Inout-Parameter: Invalid length'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $length;
    }

    /**
     * @return string
     */
    private function getParameterMode(): string
    {
        return CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_INOUT;
    }

    /**
     * @return CNull
     */
    private function getDefaultLength(): CNull
    {
        return new CNull($this->storage, $this->extra);
    }
}
