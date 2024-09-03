<?php
namespace Ntuple\Synctree\Exceptions\Inner;

use Exception;

class BizunitResponseException extends SynctreeInnerException
{
    private $statusCode;
    private $header;
    private $body;

    /**
     * BizunitResponseException constructor.
     * @param int $statusCode
     * @param array $header
     * @param mixed $body
     * @param Exception|null $ex
     */
    public function __construct(int $statusCode, array $header, $body, Exception $ex = null)
    {
        $this->statusCode = $statusCode;
        $this->header = $header;
        $this->body = $body;

        parent::__construct();
    }

    /**
     * @return int
     */
    public function getStatusCode() :int
    {
        return $this->statusCode;
    }

    /**
     * @return array
     */
    public function getHeader() :array
    {
        return $this->header;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }
}