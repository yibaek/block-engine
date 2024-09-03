<?php
namespace Ntuple\Synctree\Exceptions;

use Throwable;

class SimpleKeyException extends SynctreeException implements ISynctreeException
{
    private $data;
    private $exceptionKey = '';
    private $extraData = [];

    /**
     * SimpleKeyException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($this->makeMessage($this, $message), $code, $previous);
    }

    /**
     * @param $data
     * @return ISynctreeException
     */
    public function setData($data): ISynctreeException
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return [
            'status_code' => $this->getStatusCode() ?? 401,
            'error' => $this->data['body']['result_data']['error'] ?? '',
            'error_description' => $this->data['body']['result_data']['error_description'] ?? '',
        ];
    }

    /**
     * @return array
     */
    public function getAllData(): array
    {
        return $this->data ?? [];
    }

    /**
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->data['status-code'] ?? null;
    }

    /**
     * @param string $type
     * @param string $action
     * @return $this|ISynctreeException
     */
    public function setExceptionKey(string $type, string $action): ISynctreeException
    {
        $this->exceptionKey = $type.':'.$action;
        return $this;
    }

    /**
     * @return string
     */
    public function getExceptionKey(): string
    {
        return $this->exceptionKey;
    }

    /**
     * @param array $data
     * @return $this|ISynctreeException
     */
    public function setExtraData(array $data): ISynctreeException
    {
        $this->extraData = $data;
        return $this;
    }

    /**
     * @return array
     */
    public function getExtraData(): array
    {
        return $this->extraData;
    }
}