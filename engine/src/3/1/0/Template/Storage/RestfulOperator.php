<?php
namespace Ntuple\Synctree\Template\Storage;

class RestfulOperator implements IOperatorStorage
{
    private $requestOperator;
    private $responseOperator;

    /**
     * RestfulOperator constructor.
     * @param RequestOperator|null $requestOperator
     * @param ResponseOperator|null $responseOperator
     */
    public function __construct(RequestOperator $requestOperator = null, ResponseOperator $responseOperator = null)
    {
        $this->requestOperator = $requestOperator ?? new RequestOperator();
        $this->responseOperator = $responseOperator ?? new ResponseOperator();
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return array_merge($this->requestOperator->getData(), $this->responseOperator->getData());
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
}