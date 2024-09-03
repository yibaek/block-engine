<?php
namespace Ntuple\Synctree\Template\Storage;

class ResponseOperator implements IOperatorStorage
{
    private $response;

    /**
     * ResponseOperator constructor.
     * @param array $resHeader
     * @param $resBody
     * @param int $resStatusCode
     */
    public function __construct(array $resHeader = [], $resBody = null, int $resStatusCode = 200)
    {
        $this->response = $this->initResponse($resStatusCode, $resHeader, $resBody);
    }

    /**
     * @param array $data
     * @param bool $isAdd
     * @return IOperatorStorage
     */
    public function setHeader(array $data, bool $isAdd = false): IOperatorStorage
    {
        $this->response['header'] = (true === $isAdd) ?$this->addData($this->response['header'], $data) :$data;
        return $this;
    }

    /**
     * @param mixed $data
     * @param bool $isAdd
     * @return IOperatorStorage
     */
    public function setBody($data, bool $isAdd = false): IOperatorStorage
    {
        $this->response['body'] = (true === $isAdd) ?$this->addData($this->response['body'], $data) :$data;
        return $this;
    }

    /**
     * @param int $code
     * @return IOperatorStorage
     */
    public function setStatusCode(int $code): IOperatorStorage
    {
        $this->response['status_code'] = $code;
        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->response['header'];
    }

    /**
     * @return mixed
     */
    public function getBodys()
    {
        return $this->response['body'];
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->response['status_code'];
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return [
            'response' => $this->response
        ];
    }

    /**
     * @param int $statusCode
     * @param array $header
     * @param $body
     * @return array
     */
    private function initResponse(int $statusCode, array $header, $body = null): array
    {
        return [
            'status_code' => $statusCode,
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