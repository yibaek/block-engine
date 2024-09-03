<?php
namespace Ntuple\Synctree\Template\Storage;

class RequestOperator implements IOperatorStorage
{
    private $request;

    /**
     * RequestOperator constructor.
     * @param array $reqHeader
     * @param $reqBody
     */
    public function __construct(array $reqHeader = [], $reqBody = null)
    {
        $this->request = $this->initRequest($reqHeader, $reqBody);
    }

    /**
     * @param array $data
     * @param bool $isAdd
     * @return IOperatorStorage
     */
    public function setHeader(array $data, bool $isAdd = false): IOperatorStorage
    {
        $this->request['header'] = (true === $isAdd) ?$this->addData($this->request['header'], $data) :$data;
        return $this;
    }

    /**
     * @param $data
     * @param bool $isAdd
     * @return IOperatorStorage
     */
    public function setBody($data, bool $isAdd = false): IOperatorStorage
    {
        $this->request['body'] = (true === $isAdd) ?$this->addData($this->request['body'], $data) :$data;
        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->request['header'];
    }

    /**
     * @return mixed
     */
    public function getBodys()
    {
        return $this->request['body'];
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return [
            'request' => $this->request,
        ];
    }

    public function setStatusCode(int $code): IOperatorStorage
    {
        // TODO: Implement setStatusCode() method.
    }

    public function getStatusCode(): int
    {
        // TODO: Implement getStatusCode() method.
    }

    /**
     * @param array $header
     * @param null $body
     * @return array
     */
    private function initRequest(array $header, $body = null): array
    {
        return [
            'header' => $header,
            'body' => $body
        ];
    }

    /**
     * @param array $org
     * @param array $datas
     * @return array
     */
    private function addData(array $org, array $datas): array
    {
        foreach ($datas as $key=>$data) {
            $org[$key] = $data;
        }

        return $org;
    }
}