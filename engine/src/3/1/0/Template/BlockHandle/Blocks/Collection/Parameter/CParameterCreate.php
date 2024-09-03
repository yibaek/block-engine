<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\Parameter;

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
use Ntuple\Synctree\Util\ValidationUtil;
use Throwable;

class CParameterCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'parameter';
    public const ACTION = 'create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $key;
    private $value;
    private $description;
    private $datatype;
    private $required;

    /**
     * CParameterCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $key
     * @param IBlock|null $value
     * @param IBlock|null $description
     * @param IBlock|null $datatype
     * @param IBlock|null $required
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $key = null, IBlock $value = null, IBlock $description = null, IBlock $datatype = null, IBlock $required= null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->key = $key;
        $this->value = $value;
        $this->description = $description;
        $this->datatype = $datatype;
        $this->required = $required;
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
        $this->key = $this->setBlock($this->storage, $data['template']['key']);
        $this->value = $this->setBlock($this->storage, $data['template']['value']);
        $this->description = $this->setBlock($this->storage, $data['template']['description']);
        $this->datatype = $this->setBlock($this->storage, $data['template']['datatype']);
        $this->required = $this->setBlock($this->storage, $data['template']['required']);

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
                'key' => $this->key->getTemplate(),
                'value' => $this->value->getTemplate(),
                'description' => $this->description->getTemplate(),
                'datatype' => $this->datatype->getTemplate(),
                'required' => $this->required->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return CParameter
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): CParameter
    {
        try {
            return new CParameter(
                $this->getKey($blockStorage),
                $this->getValue($blockStorage),
                $this->getDataType($blockStorage),
                $this->getRequired($blockStorage),
                $this->getDescription($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Parameter-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return int|string
     * @throws ISynctreeException
     */
    private function getKey(array &$blockStorage)
    {
        try {
            return ValidationUtil::validateArrayKey($this->key->do($blockStorage));
        } catch (\InvalidArgumentException $ex) {
            throw (new InvalidArgumentException('Parameter-Create: Invalid key: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
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
     * @return string|null
     * @throws ISynctreeException
     */
    private function getDataType(array &$blockStorage): ?string
    {
        $datatype = $this->datatype->do($blockStorage);
        if (!is_null($datatype)) {
            if (!is_string($datatype)) {
                throw (new InvalidArgumentException('Parameter-Create: Invalid datatype'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $datatype;
    }

    /**
     * @param array $blockStorage
     * @return bool|null
     * @throws ISynctreeException
     */
    private function getRequired(array &$blockStorage): ?bool
    {
        $required = $this->required->do($blockStorage);
        if (!is_null($required)) {
            if (!is_bool($required)) {
                throw (new InvalidArgumentException('Parameter-Create: Invalid required'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $required;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getDescription(array &$blockStorage): ?string
    {
        $description = $this->description->do($blockStorage);
        if (!is_null($description)) {
            if (!is_string($description)) {
                throw (new InvalidArgumentException('Parameter-Create: Invalid description'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $description;
    }
}