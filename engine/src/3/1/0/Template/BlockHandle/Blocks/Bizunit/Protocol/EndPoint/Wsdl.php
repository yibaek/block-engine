<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\EndPoint;

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

class Wsdl implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'protocol-end-point';
    public const ACTION = 'wsdl';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $wsdl;

    /**
     * Wsdl constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $wsdl
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $wsdl = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->wsdl = $wsdl;
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
        $this->wsdl = $this->setBlock($this->storage, $data['template']['wsdl']);

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
            'template' => [
                'wsdl' => $this->wsdl->getTemplate()
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
            return $this->getWsdl($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Wsdl'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getWsdl(array &$blockStorage): string
    {
        $wsdl = $this->wsdl->do($blockStorage);
        if (!is_string($wsdl)) {
            throw (new InvalidArgumentException('Wsdl: Invalid wsdl: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $wsdl;
    }
}