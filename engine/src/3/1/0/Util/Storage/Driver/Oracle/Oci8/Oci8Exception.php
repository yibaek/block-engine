<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Oracle\Oci8;

use RuntimeException;

class Oci8Exception extends RuntimeException
{
    private $data;

    /**
     * @param string $message
     * @param string|null $code
     * @return Oci8Exception
     */
    public function setData(string $message, string $code = null): self
    {
        $this->data = [
            'code' => $code ?? '',
            'message' => $message
        ];
        return $this;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data ?? [];
    }
}