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
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class ProcedureCreateEx implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'statement-procedure-create-ex';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $procedure;
    private $param;

    /**
     * ProcedureCreateEx constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $procedure
     * @param IBlock|null $param
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $procedure = null, IBlock $param = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->procedure = $procedure;
        $this->param = $param;
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
        $this->procedure = $this->setBlock($this->storage, $data['template']['procedure']);
        $this->param = $this->setBlock($this->storage, $data['template']['param']);

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
                'procedure' => $this->procedure->getTemplate(),
                'param' => $this->param->getTemplate()
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
                $this->getProcedure($blockStorage),
                $this->getParam($blockStorage)
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Procedure-Create-Ex'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getProcedure(array &$blockStorage): string
    {
        $procedure = $this->procedure->do($blockStorage);
        if (!is_string($procedure)) {
            throw (new InvalidArgumentException('Storage-Procedure-Create-Ex: Invalid procedure type: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $procedure;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getParam(array &$blockStorage): array
    {
        $param = $this->param->do($blockStorage);
        if (!is_null($param)) {
            if (!is_array($param)) {
                throw (new InvalidArgumentException('Storage-Procedure-Create-Ex: Invalid param type: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $param ?? [];
    }
}