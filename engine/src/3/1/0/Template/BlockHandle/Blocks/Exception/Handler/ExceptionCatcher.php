<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Exception\Handler;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CExceptionType;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Template\Storage\ExceptionStorage;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use ReflectionClass;
use Throwable;

class ExceptionCatcher implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'exception-handler';
    public const ACTION = 'exception-catcher';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $id;
    private $exceptions;
    private $statements;
    private $occurException;
    private $isSetBaseException;

    /**
     * ExceptionCatcher constructor.
     * @param PlanStorage $storage
     * @param string|null $id
     * @param ExtraManager|null $extra
     * @param BlockAggregator|null $exceptions
     * @param BlockAggregator|null $statements
     */
    public function __construct(PlanStorage $storage, string $id = null, ExtraManager $extra = null, BlockAggregator $exceptions = null, BlockAggregator $statements = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->id = $id;
        $this->exceptions = $exceptions;
        $this->statements= $statements;
        $this->isSetBaseException = false;
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
        $this->id = $data['template']['id'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->exceptions = $this->setBlocks($this->storage, $data['template']['exceptions']);
        $this->statements = $this->setBlocks($this->storage, $data['template']['statements']);

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
                'id' => $this->id,
                'exceptions' => $this->getTemplateEachException(),
                'statements' => $this->getTemplateEachStatement()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return bool
     * @throws Throwable
     */
    public function do(array &$blockStorage): bool
    {
        $isCatch = false;

        // get exceptions
        $exceptions = $this->getExceptions($blockStorage);

        // check base exception
        if (true === $this->isSetBaseException) {
            if (false === $this->statements->isEmpty()) {
                $this->storage->addOperatorStorage($this->id, new ExceptionStorage($this->storage, $this->occurException));
                $isCatch = true;
            }

            foreach ($this->statements as $statement) {
                $statement->do($blockStorage);
            }

            return $isCatch;
        }

        // check other exception
        $occurException = (new ReflectionClass($this->occurException))->getShortName();
        if (true === in_array($occurException, $exceptions, true)) {
            if (false === $this->statements->isEmpty()) {
                $this->storage->addOperatorStorage($this->id, new ExceptionStorage($this->storage, $this->occurException));
                $isCatch = true;
            }

            foreach ($this->statements as $statement) {
                $statement->do($blockStorage);
            }
        }

        return $isCatch;
    }

    /**
     * @param Throwable $ex
     */
    public function setException(Throwable $ex): void
    {
        $this->occurException = $ex;
    }

    /**
     * @return array
     */
    private function getTemplateEachException(): array
    {
        $resData = [];
        foreach ($this->exceptions as $exception) {
            $resData[] = $exception->getTemplate();
        }

        return $resData;
    }

    /**
     * @return array
     */
    private function getTemplateEachStatement(): array
    {
        $resData = [];
        foreach ($this->statements as $statement) {
            $resData[] = $statement->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return array
     */
    private function getExceptions(array &$blockStorage): array
    {
        $resData = [];
        foreach ($this->exceptions as $exception) {
            $exceptionName = $exception->do($blockStorage);

            // check base exception
            if (CExceptionType::EXCEPTION_TYPE_BASE_EXCEPTION === $exceptionName) {
                $this->isSetBaseException = true;
            }

            $resData[] = $exceptionName;
        }

        return $resData;
    }
}