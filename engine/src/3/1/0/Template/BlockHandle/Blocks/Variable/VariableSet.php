<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Variable;

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

class VariableSet implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'variable';
    public const ACTION = 'set';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $name;
    private $value;

    /**
     * VariableSet constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $name
     * @param IBlock|null $value
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $name = null, IBlock $value = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->name = $name;
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
                'value' => $this->value->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): void
    {
        try {
            foreach($this->storage->getStackManager()->toArray() as $stack) {
                $key = $this->getKey($blockStorage);
                if (array_key_exists($key, $stack->data)) {
                    $stack->data[$key] = $this->getValue($blockStorage);
                    return;
                }
            }

            $key = $this->getKey($blockStorage);
            if (!array_key_exists($key, $blockStorage)) {
                throw (new InvalidArgumentException('Variable-Set: Not found data: '.$key))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            $blockStorage[$key] = $this->getValue($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Variable-Set'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            return ValidationUtil::validateArrayKey($this->name->do($blockStorage));
        } catch (\InvalidArgumentException $ex) {
            throw (new InvalidArgumentException('Variable-Set: Invalid key: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
}