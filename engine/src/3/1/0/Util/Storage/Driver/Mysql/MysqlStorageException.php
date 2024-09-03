<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Mysql;

use Ntuple\Synctree\Util\Storage\Exception\UtilStorageException;

class MysqlStorageException extends UtilStorageException
{
    private $data;

    /**
     * @param array $data
     * @return MysqlStorageException
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