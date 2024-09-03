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

class ProcedureCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'statement-procedure-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $procedure;
    private $inputParam;
    private $outputParam;

    /**
     * ProcedureCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $procedure
     * @param IBlock|null $inputParam
     * @param IBlock|null $outputParam
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $procedure = null, IBlock $inputParam = null, IBlock $outputParam = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->procedure = $procedure;
        $this->inputParam = $inputParam;
        $this->outputParam = $outputParam;
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
        $this->inputParam = $this->setBlock($this->storage, $data['template']['input-param']);
        $this->outputParam = $this->setBlock($this->storage, $data['template']['output-param']);

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
                'input-param' => $this->inputParam->getTemplate(),
                'output-param' => $this->outputParam->getTemplate()
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
            throw (new RuntimeException('Storage-Procedure-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
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
            throw (new InvalidArgumentException('Storage-Procedure-Create: Invalid procedure type: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $procedure;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getInputParam(array &$blockStorage): array
    {
        $param = $this->inputParam->do($blockStorage);
        if (!is_null($param)) {
            if (!is_array($param)) {
                throw (new InvalidArgumentException('Storage-Procedure-Create: Invalid input-param type: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $param ?? [];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getOutputParam(array &$blockStorage): array
    {
        $param = $this->outputParam->do($blockStorage);
        if (!is_null($param)) {
            if (!is_array($param)) {
                throw (new InvalidArgumentException('Storage-Procedure-Create: Invalid output-param type: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $param ?? [];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getParam(array &$blockStorage): array
    {
        return array_merge($this->getInputParam($blockStorage), $this->getOutputParam($blockStorage));
    }
}