<?php declare(strict_types=1);

namespace Ntuple\Synctree\Util\Storage\Driver\Postgres;

use Ntuple\Synctree\Util\Storage\Exception\UtilStorageException;

class PostgresStorageException extends UtilStorageException
{
    private $data;

    /**
     * @param array $data
     * @return PostgresStorageException
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