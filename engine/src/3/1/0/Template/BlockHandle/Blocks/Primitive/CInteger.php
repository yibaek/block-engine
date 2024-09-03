<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive;

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

class CInteger implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'primitive';
    public const ACTION = 'integer';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $value;

    /**
     * CInteger constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param int|null $value
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, int $value = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->value = $value;
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
        $this->value = $data['template']['value'];

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
                'value' => $this->value
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): int
    {
        try {
            if (!is_int($this->value)) {
                throw (new InvalidArgumentException('Primitive-Integer: Not a integer type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
            return $this->value;
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Primitive-Integer'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }
}