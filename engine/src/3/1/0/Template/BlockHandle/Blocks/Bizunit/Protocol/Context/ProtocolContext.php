<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Context;

class ProtocolContext
{
    private $statusCode;
    private $header;
    private $body;

    /**
     * ProtocolContext constructor.
     * @param array $header
     * @param mixed $body
     * @param int|null $statusCode
     */
    public function __construct(array $header, $body, int $statusCode = null)
    {
        $this->statusCode = $statusCode;
        $this->header = $header;
        $this->body = $body;
    }

    /**
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * @return array
     */
    public function getHeader(): array
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

    /**
     * @return array
     */
    public function getData(): array
    {
        return [
            'status_code' => $this->getStatusCode(),
            'header' => $this->getHeader(),
            'body' => $this->getBody()
        ];
    }
}