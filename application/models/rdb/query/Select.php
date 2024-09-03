<?php
namespace models\rdb\query;

abstract class Select extends Query
{
    private const TYPE = 'SELECT';

    private $select;

    /**
     * Select constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->initSelect();
    }

    /**
     * @param string $column
     * @return $this
     */
    public function select(string $column): self
    {
        $this->select['data'][] = [
            'column' => $column
        ];
        return $this;
    }

    /**
     * @param string $column
     * @return $this
     */
    public function selectDatetime(string $column): self
    {
        return $this->select($column);
    }

    /**
     * @param string $column
     * @param string $key
     * @param string|null $alias
     * @return $this
     */
    public function selectAESDecrypt(string $column, string $key, string $alias): self
    {
        return $this->select('AES_DECRYPT('.$column.', UNHEX(SHA2('.$key.', 512))) AS '.$alias);
    }

    /**
     * @param string $alias
     * @param string $column
     * @return $this
     */
    public function selectCount(string $alias, string $column = '*') :self
    {
        return $this->select('count('.$column.') AS '.$alias);
    }

    /**
     * @return $this
     */
    public function selectAll() :self
    {
        return $this->select('*');
    }

    public function initSelect(): void
    {
        $this->select = ['data' => [], 'delimiter' => ', '];
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
        // select
        if (!empty($this->select['data'])) {
            if (!empty($this->query)) {
                $this->query .= ' SELECT';
            } else {
                $this->query = 'SELECT';
            }

            $columns = [];
            foreach ($this->select['data'] as $data) {
                $columns[] = $data['column'];
            }
            $this->query .= ' ' . implode($this->select['delimiter'], $columns);
            $this->initSelect();
        }

        // from
        if (!empty($this->table['data'])) {
            $tables = [];
            foreach ($this->table['data'] as $data) {
                $tables[] = $data['table'];
            }
            $this->query .= ' FROM ' . implode($this->table['delimiter'], $tables);
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

        // where
        if (!empty($this->where['data'])) {
            $conditions = [];
            foreach ($this->where['data'] as $data) {
                $conditions[] = $data['operator'] !== null ?$data['operator'].' '.$data['condition'] :''.$data['condition'];
            }
            $this->query .= ' WHERE ' . implode(' ', $conditions);
            $this->initWhere();
        }

        // group by
        if (!empty($this->groupby['data'])) {
            $columns = [];
            foreach ($this->groupby['data'] as $data) {
                $columns[] = $data['column'];
            }
            $this->query .= ' GROUP BY ' . implode($this->groupby['delimiter'], $columns);
            $this->initGroupBy();
        }

        // having
        if (!empty($this->having['data'])) {
            $conditions = [];
            foreach ($this->having['data'] as $data) {
                $conditions[] = $data['operator'] !== null ?$data['operator'].' '.$data['condition'] :''.$data['condition'];
            }
            $this->query .= ' HAVING ' . implode(' ', $conditions);
            $this->initHaving();
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