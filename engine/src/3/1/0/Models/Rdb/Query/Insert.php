<?php
namespace Ntuple\Synctree\Models\Rdb\Query;

abstract class Insert extends Select
{
    private const TYPE = 'INSERT';

    private $insert;

    /**
     * Insert constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->initInsert();
    }

    /**
     * @param string|null $column
     * @param null $value
     * @param string|null $bindvalue
     * @return $this
     */
    public function insert(string $column = null, $value = null, string $bindvalue = null): self
    {
        $this->insert['data'][] = [
            'column' => $column,
            'value' => $bindvalue ?? ':'.$this->getColumnName($column)
        ];
        $this->setValues($this->getColumnName($column), $value);
        return $this;
    }

    /**
     * @param string|null $column
     * @param string|null $value
     * @param string|null $bindvalue
     * @return $this
     */
    public function insertDatetime(string $column = null, string $value = null, string $bindvalue = null): self
    {
        return $this->insert($column, $value, $bindvalue);
    }

    public function initInsert(): void
    {
        $this->insert = ['data' => [], 'delimiter' => ', '];
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        $this->query = self::TYPE.' INTO';

        // to
        $this->query .= ' '.$this->table['data'][0]['table'];
        $this->initTable();

        if (!empty($this->insert['data'])) {
            $values = [];
            $columns = [];
            foreach ($this->insert['data'] as $data) {
                if (!empty($data['column'])) {
                    $columns[] = $data['column'];
                }
                if (!empty($data['value'])) {
                    $values[] = $data['value'];
                }
            }
            if (!empty($data['column'])) {
                $this->query .= '('.implode($this->insert['delimiter'], $columns).')';
            }
            if (!empty($data['value'])) {
                $this->query .= ' VALUES('.implode($this->insert['delimiter'], $values).')';
            }
            $this->initInsert();
        }

        return parent::getQuery();
    }
}