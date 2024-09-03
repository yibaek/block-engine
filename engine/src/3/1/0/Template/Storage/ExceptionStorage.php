<?php
namespace Ntuple\Synctree\Template\Storage;

use Exception;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use ReflectionClass;
use ReflectionException;

class ExceptionStorage implements IOperatorStorage
{
    private $storage;
    private $exception;

    /**
     * ExceptionStorage constructor.
     * @param PlanStorage $planStorage
     * @param Exception $exception
     */
    public function __construct(PlanStorage $planStorage, Exception $exception)
    {
        $this->storage = $planStorage;
        $this->exception = $exception;
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function getData(): array
    {
        return [
            'name' => $this->getExceptionName(),
            'message' => $this->exception->getMessage(),
            'data' => $this->exception instanceof ISynctreeException ?$this->exception->getData() :[]
        ];
    }

    public function setHeader(array $data, bool $isAdd = false): IOperatorStorage
    {
        // TODO: Implement setHeader() method.
    }

    public function setBody(array $data, bool $isAdd = false): IOperatorStorage
    {
        // TODO: Implement setBody() method.
    }

    public function setStatusCode(int $code): IOperatorStorage
    {
        // TODO: Implement setStatusCode() method.
    }

    public function getHeaders(): array
    {
        // TODO: Implement getHeaders() method.
    }

    public function getBodys(): array
    {
        // TODO: Implement getBodys() method.
    }

    public function getStatusCode(): int
    {
        // TODO: Implement getStatusCode() method.
    }

    /**
     * @return string
     */
    public function getExceptionKey(): string
    {
        return $this->exception instanceof ISynctreeException ?$this->exception->getExceptionKey() :'';
    }

    /**
     * @return array
     */
    public function getExtraData(): array
    {
        return $this->exception instanceof ISynctreeException ?$this->exception->getExtraData() :[];
    }

    /**
     * @return string
     * @throws ReflectionException
     */
    private function getExceptionName(): string
    {
        $exceptionName = (new ReflectionClass($this->exception))->getShortName();
        return 'ErrorException' === $exceptionName ?'Exception' :$exceptionName;
    }
}