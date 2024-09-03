<?php
namespace Ntuple\Synctree\Plan\Unit;

class ProxyManager
{
    private $path;
    private $method;
    private $requestTime;
    private $logMessage;

    /**
     * @param string|null $path
     * @return $this
     */
    public function setPath(string $path = null): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path ?? '';
    }

    /**
     * @param string|null $method
     * @return $this
     */
    public function setMethod(string $method = null): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method ?? '';
    }

    /**
     * @param float|null $requestTime
     * @return $this
     */
    public function setRequestTime(float $requestTime = null): self
    {
        $this->requestTime = $requestTime;
        return $this;
    }

    /**
     * @return float
     */
    public function getRequestTime(): float
    {
        return $this->requestTime ?? 0;
    }

    /**
     * @param array|null $message
     * @return $this
     */
    public function setLogMessage(array $message = null): self
    {
        $this->logMessage = $message;
        return $this;
    }

    /**
     * @return array
     */
    public function getLogMessage(): array
    {
        return $this->logMessage ?? [];
    }
}