<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\ObjectNotation;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\Markup\Xml\XmlEncoder;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Throwable;

class CXmlEncode implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'object-notation-xml-encode';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $value;
    private $rootElement;
    private $encoding;
    private $version;

    /**
     * CXmlEncode constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $value
     * @param IBlock|null $rootElement
     * @param IBlock|null $encoding
     * @param IBlock|null $version
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $value = null, IBlock $rootElement = null, IBlock $encoding = null, IBlock $version = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->value = $value;
        $this->rootElement = $rootElement;
        $this->encoding = $encoding;
        $this->version = $version;
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
        $this->rootElement = $this->setBlock($this->storage, $data['template']['root']);
        $this->encoding = $this->setBlock($this->storage, $data['template']['encoding']);
        $this->version = $this->setBlock($this->storage, $data['template']['version']);

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
                'root' => $this->rootElement->getTemplate(),
                'encoding' => $this->encoding->getTemplate(),
                'version' => $this->version->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): string
    {
        try {
            return (new XmlEncoder($this->getValue($blockStorage), $this->getRootElement($blockStorage), $this->getEncoding($blockStorage), $this->getVersion($blockStorage)))->convert();
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-Xml-Encode'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getValue(array &$blockStorage): array
    {
        $value = $this->value->do($blockStorage);
        if (!is_array($value)) {
            throw (new InvalidArgumentException('Util-Xml-Encode: Invalid value: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $value;
    }

    /**
     * @param array $blockStorage
     * @return array|string
     * @throws ISynctreeException
     */
    private function getRootElement(array &$blockStorage)
    {
        $rootElement = $this->rootElement->do($blockStorage);
        if (!is_array($rootElement) && !is_string($rootElement)) {
            throw (new InvalidArgumentException('Util-Xml-Encode: Invalid root element: Not a string or array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $rootElement;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getEncoding(array &$blockStorage): string
    {
        $encoding = $this->encoding->do($blockStorage);
        if (!is_string($encoding)) {
            throw (new InvalidArgumentException('Util-Xml-Encode: Invalid encoding: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $encoding;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getVersion(array &$blockStorage): string
    {
        $version = $this->version->do($blockStorage);
        if (!is_string($version)) {
            throw (new InvalidArgumentException('Util-Xml-Encode: Invalid version: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $version;
    }
}