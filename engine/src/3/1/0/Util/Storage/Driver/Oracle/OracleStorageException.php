<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Oracle;

use Ntuple\Synctree\Util\Storage\Exception\UtilStorageException;

class OracleStorageException extends UtilStorageException
{
    private $data;

    /**
     * @param array $data
     * @return OracleStorageException
     */
    public function setData(array $data): self
    {
        $this->data = $data;
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