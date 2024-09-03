<?php
namespace Ntuple\Synctree\Models\Rdb\Query;

abstract class Delete extends Query
{
    private const TYPE = 'DELETE';

    private $delete;

    /**
     * Delete constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->initDelete();
    }

    public function initDelete(): void
    {
        $this->delete = ['data' => [], 'delimiter' => ', '];
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

        // where
        if (!empty($this->where['data'])) {
            $conditions = [];
            foreach ($this->where['data'] as $data) {
                $conditions[] = $data['operator'] !== null ?$data['operator'].' '.$data['condition'] :''.$data['condition'];
            }
            $this->query .= ' WHERE ' . implode(' ', $conditions);
            $this->initWhere();
        }

        return parent::getQuery();
    }
}