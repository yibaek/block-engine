<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Unit\Soap;

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

class SoapHeaderCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'soap-represents';
    public const ACTION = 'header';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $namespace;
    private $name;
    private $header;
    private $mustunderstand;

    /**
     * SoapHeaderCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $namespace
     * @param IBlock|null $name
     * @param IBlock|null $header
     * @param IBlock|null $mustunderstand
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $namespace = null, IBlock $name = null, IBlock $header = null, IBlock $mustunderstand = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->namespace = $namespace;
        $this->name = $name;
        $this->header = $header;
        $this->mustunderstand = $mustunderstand;
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
        $this->namespace = $this->setBlock($this->storage, $data['template']['namespace']);
        $this->name = $this->setBlock($this->storage, $data['template']['name']);
        $this->header = $this->setBlock($this->storage, $data['template']['header']);
        $this->mustunderstand = $this->setBlock($this->storage, $data['template']['mustunderstand']);

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
                'namespace' => $this->namespace->getTemplate(),
                'name' => $this->name->getTemplate(),
                'header' => $this->header->getTemplate(),
                'mustunderstand' => $this->mustunderstand->getTemplate()
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
                $this->getNamespace($blockStorage),
                $this->getName($blockStorage),
                $this->getHeader($blockStorage),
                $this->getMustunderstand($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('SoapHeader'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getNamespace(array &$blockStorage): string
    {
        $namespace = $this->namespace->do($blockStorage);
        if (!is_string($namespace)) {
            throw (new InvalidArgumentException('SoapHeader: Invalid namespace: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $namespace;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getName(array &$blockStorage): string
    {
        $name = $this->name->do($blockStorage);
        if (!is_string($name)) {
            throw (new InvalidArgumentException('SoapHeader: Invalid name: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $name;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getHeader(array &$blockStorage): array
    {
        $header = $this->header->do($blockStorage);
        if (!is_array($header)) {
            throw (new InvalidArgumentException('SoapHeader: Invalid header: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $header;
    }

    /**
     * @param array $blockStorage
     * @return bool
     * @throws ISynctreeException
     */
    private function getMustunderstand(array &$blockStorage): bool
    {
        $mustunderstand = $this->mustunderstand->do($blockStorage);
        if (!is_bool($mustunderstand)) {
            throw (new InvalidArgumentException('SoapHeader: Invalid must understand: Not a boolean type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $mustunderstand;
    }
}