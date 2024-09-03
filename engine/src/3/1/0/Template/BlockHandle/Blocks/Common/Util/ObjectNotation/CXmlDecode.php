<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\ObjectNotation;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\Markup\Xml\XmlDecoder;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Throwable;

class CXmlDecode implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'object-notation-xml-decode';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $value;
    private $replcePrefix;

    /**
     * CXmlDecode constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $value
     * @param IBlock|null $replcePrefix
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $value = null, IBlock $replcePrefix = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->value = $value;
        $this->replcePrefix = $replcePrefix;
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
        $this->replcePrefix = $this->setBlock($this->storage, $data['template']['replace-prefix']);

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
                'replace-prefix' => $this->replcePrefix->getTemplate()
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
            return (new XmlDecoder($this->getValue($blockStorage)))->setReplacePrefixByEmptyStringInNodeNames($this->getReplcePrefix($blockStorage))->convert();
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-Xml-Decode'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getValue(array &$blockStorage): string
    {
        $value = $this->value->do($blockStorage);
        if (!is_string($value)) {
            throw (new InvalidArgumentException('Util-Xml-Decode: Invalid value: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $value;
    }

    /**
     * @param array $blockStorage
     * @return bool
     * @throws ISynctreeException
     */
    private function getReplcePrefix(array &$blockStorage): bool
    {
        $replcePrefix = $this->replcePrefix->do($blockStorage);
        if (!is_bool($replcePrefix)) {
            throw (new InvalidArgumentException('Util-Xml-Decode: Invalid replace prefix: Not a boolean type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $replcePrefix;
    }
}