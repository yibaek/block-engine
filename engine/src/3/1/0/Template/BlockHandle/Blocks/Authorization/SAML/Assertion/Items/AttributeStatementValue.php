<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Items;

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

class AttributeStatementValue implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-assertion-item-attribute-statement-value';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $name;
    private $format;
    private $value;

    /**
     * AttributeStatementValue constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $name
     * @param IBlock|null $format
     * @param IBlock|null $value
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $name = null, IBlock $format = null, IBlock $value = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->name = $name;
        $this->format = $format;
        $this->value = $value;
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
        $this->name = $this->setBlock($this->storage, $data['template']['name']);
        $this->format = $this->setBlock($this->storage, $data['template']['format']);
        $this->value = $this->setBlock($this->storage, $data['template']['value']);

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
                'name' => $this->name->getTemplate(),
                'format' => $this->format->getTemplate(),
                'value' => $this->value->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): array
    {
        try {
            return [
                'name' => $this->getName($blockStorage),
                'format' => $this->getFormat($blockStorage),
                'value' => $this->getValue($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Assertion-Item-AttributeStatement-Value'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getName(array &$blockStorage): ?string
    {
        $name = $this->name->do($blockStorage);
        if (!is_null($name)) {
            if (!is_string($name)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Item-AttributeStatement-Value: Invalid name: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $name;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getFormat(array &$blockStorage): ?string
    {
        $format = $this->format->do($blockStorage);
        if (!is_null($format)) {
            if (!is_string($format)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Item-AttributeStatement-Value: Invalid format: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $format;
    }

    /**
     * @param array $blockStorage
     * @return string|array|null
     * @throws ISynctreeException
     */
    private function getValue(array &$blockStorage)
    {
        $value = $this->value->do($blockStorage);
        if (!is_null($value)) {
            if (!is_string($value) && !is_array($value)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Item-AttributeStatement-Value: Invalid value: Not a string or array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $value;
    }
}