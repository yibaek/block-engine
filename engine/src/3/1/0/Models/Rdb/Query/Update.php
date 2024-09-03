<?php
namespace Ntuple\Synctree\Models\Rdb\Query;

abstract class Update extends Query
{
    private const TYPE = 'UPDATE';

    private $update;

    /**
     * Update constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->initUpdate();
    }

    /**
     * @param string $column
     * @param $value
     * @param string|null $bindvalue
     * @return $this
     */
    public function update(string $column, $value, string $bindvalue = null): self
    {
        $this->update['data'][] = [
            'column' => $column,
            'value' => $bindvalue ?? ':'.$this->getColumnName($column)
        ];
        $this->setValues($this->getColumnName($column), $value);
        return $this;
    }

    /**
     * @param string $column
     * @param string $value
     * @param string|null $bindvalue
     * @return $this
     */
    public function updateDatetime(string $column, string $value, string $bindvalue = null): self
    {
        return $this->update($column, $value, $bindvalue);
    }

    public function initUpdate(): void
    {
        $this->update = ['data' => [], 'delimiter' => ', '];
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
        $this->query = self::TYPE;

        // from
        if (!empty($this->table['data'])) {
            $tables = [];
            foreach ($this->table['data'] as $data) {
                $tables[] = $data['table'];
            }
            $this->query .= ' ' . implode($this->table['delimiter'], $tables);
            $this->initTable();
        }

        // hint
        if (!empty($this->indexhint['data'])) {
            $hints = [];
            foreach ($this->indexhint['data'] as $data) {
                $hints[] = $data['hint'].' ('.implode(',',$data['index']).')';
            }
            $this->query .= ' '.implode(' ', $hints);
            $this->initIndexhint();
        }

        // join
        if (!empty($this->join['data'])) {
            $joins = [];
            foreach ($this->join['data'] as $data) {
                $joins[] = implode(' ', [$data['type'],$data['table'],'ON '.$data['condition']]);
            }
            $this->query .= ' '.implode(' ', $joins);
            $this->initJoin();
        }

        // update
        if (!empty($this->update['data'])) {
            $statements = [];
            foreach ($this->update['data'] as $data) {
                $statements[] = $data['column'].'='.$data['value'];
            }
            $this->query .= ' SET ' . implode($this->update['delimiter'], $statements);
            $this->initJoin();
        }

        // where
        if (!empty($this->where['data'])) {
            $conditions = [];
            foreach ($this->where['data'] as $data) {
                $conditions[] = $data['operator'] !== null ?$data['operator'].' '.$data['condition'] :''.$data['condition'];
            }
            $this->query .= ' WHERE ' . implode(' ', $conditions);
            $this->initWhere();
        }

        // order by
        if (!empty($this->orderby['data'])) {
            $columns = [];
            foreach ($this->orderby['data'] as $data) {
                $columns[] = $data['order'] !== null ?$data['column'].' '.$data['order'] :''.$data['column'];
            }
            $this->query .= ' ORDER BY ' . implode($this->orderby['delimiter'], $columns);
            $this->initOrderBy();
        }

        // limit
        if (!empty($this->limit)) {
            $limit = $this->limit['offset'] !== null ?$this->limit['offset'].', '.$this->limit['limit'] :$this->limit['limit'];
            $this->query .= ' LIMIT ' . $limit;
            $this->initLimit();
        }

        return parent::getQuery();
    }
}