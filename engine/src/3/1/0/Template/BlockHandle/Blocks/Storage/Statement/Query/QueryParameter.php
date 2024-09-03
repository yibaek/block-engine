<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Statement\Query;

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

class QueryParameter implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'statement-query-parameter';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $bindName;
    private $value;
    private $parameterType;

    /**
     * QueryParameter constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $bindName
     * @param IBlock|null $value
     * @param IBlock|null $parameterType
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $bindName = null, IBlock $value = null, IBlock $parameterType = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->bindName = $bindName;
        $this->value = $value;
        $this->parameterType = $parameterType;
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
        $this->bindName = $this->setBlock($this->storage, $data['template']['bind-name']);
        $this->value = $this->setBlock($this->storage, $data['template']['value']);
        $this->parameterType = $this->setBlock($this->storage, $data['template']['parameter-type']);

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
                'bind-name' => $this->bindName->getTemplate(),
                'value' => $this->value->getTemplate(),
                'parameter-type' => $this->parameterType->getTemplate()
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
                $this->getBindName($blockStorage),
                $this->getValue($blockStorage),
                $this->getParameterType($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Query-Parameter'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return mixed
     * @throws ISynctreeException
     */
    private function getBindName(array &$blockStorage): string
    {
        $bindName = $this->bindName->do($blockStorage);
        if (!is_string($bindName)) {
            throw (new InvalidArgumentException('Storage-Query-Parameter: Invalid bindname type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $bindName;
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
    private function getParameterType(array &$blockStorage): ?string
    {
        $parameterType = $this->parameterType->do($blockStorage);
        if (!is_null($parameterType)) {
            if (!is_string($parameterType)) {
                throw (new InvalidArgumentException('Storage-Query-Parameter: Invalid parameter type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $parameterType;
    }
}
