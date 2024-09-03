<?php
namespace models\rdb\query;

use models\rdb\query\parameter\Parameter;
use models\rdb\query\parameter\ParameterManager;

abstract class Query implements IQuery
{
    protected $values;
    protected $query;
    protected $rawQuery;
    protected $table;
    protected $indexhint;
    protected $join;
    protected $where;
    protected $groupby;
    protected $having;
    protected $orderby;
    protected $limit;

    /**
     * Query constructor.
     */
    public function __construct()
    {
        $this->query = '';
        $this->values = new ParameterManager();
        $this->table = ['data' => [], 'delimiter' => ', '];
        $this->indexhint = ['data' => []];
        $this->join = ['data' => []];
        $this->where = ['data' => []];
        $this->groupby = ['data' => [], 'delimiter' => ', '];
        $this->having = ['data' => []];
        $this->orderby = ['data' => [], 'delimiter' => ', '];
    }

    /**
     * @param string $query
     * @return $this
     */
    public function putQuery(string $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        $this->buildRawQuery();

        return $this->query;
    }

    /**
     * @param string $column
     * @param $value
     */
    public function setValues(string $column, $value): void
    {
        $this->values->addParameter(new Parameter($column, $value));
    }

    /**
     * @return ParameterManager
     */
    public function getValues(): ParameterManager
    {
        return $this->values;
    }

    /**
     * @param string $table
     * @return $this
     */
    public function table(string $table): self
    {
        $parts = explode(' ', $table);
        $this->table['data'][] = [
            'table' => $table,
            'alias' => end($parts)
        ];
        return $this;
    }

    public function initTable(): void
    {
        $this->table = ['data' => [], 'delimiter' => ', '];
    }

    /**
     * @param string $hint
     * @param array $indexs
     * @return $this
     */
    public function indexhint(string $hint, array $indexs): self
    {
        $this->indexhint['data'][] = [
            'hint' => $hint,
            'index' => $indexs
        ];
        return $this;
    }

    public function initIndexhint(): void
    {
        $this->indexhint = ['data' => []];
    }

    /**
     * @param string $type
     * @param string $table
     * @param string $condition
     * @return $this
     */
    public function join(string $type, string $table, string $condition): self
    {
        $this->join['data'][] = [
            'type' => $type,
            'table' => $table,
            'condition' => $condition
        ];
        return $this;
    }

    public function initJoin(): void
    {
        $this->join = ['data' => []];
    }

    /**
     * @param string $column
     * @param null $value
     * @param string $operator
     * @param string|null $logic
     * @return $this
     */
    public function where(string $column, $value = null, string $operator = '=', string $logic = null): self
    {
        $condition = $column;
        if ($value !== null) {
            $condition .= $operator.':'.$this->getColumnName($column);
            $this->setValues($this->getColumnName($column), $value);
        }

        $this->where['data'][] = [
            'condition' => $condition,
            'operator' => $logic
        ];
        return $this;
    }

    /**
     * @param string $column
     * @param null $value
     * @param string $operator
     * @return $this
     */
    public function whereOr(string $column, $value = null, string $operator = '='): self
    {
        return $this->where($column, $value, $operator, 'OR');
    }

    /**
     * @param string $column
     * @param null $value
     * @param string $operator
     * @return $this
     */
    public function whereAnd(string $column, $value = null, string $operator = '='): self
    {
        return $this->where($column, $value, $operator, 'AND');
    }

    public function initWhere(): void
    {
        $this->where = ['data' => []];
    }

    /**
     * @param array $columns
     * @return $this
     */
    public function groupBy(array $columns): self
    {
        foreach ($columns as $column) {
            $this->groupby['data'][] = [
                'column' => $column
            ];
        }
        return $this;
    }

    public function initGroupBy(): void
    {
        $this->groupby = ['data' => [], 'delimiter' => ', '];
    }

    /**
     * @param string $column
     * @param string $order
     * @return $this
     */
    public function orderBy(string $column, string $order = 'ASC'): self
    {
        $this->orderby['data'][] = [
            'column' => $column,
            'order' => $order
        ];
        return $this;
    }

    public function initOrderBy(): void
    {
        $this->orderby = ['data' => [], 'delimiter' => ', '];
    }

    /**
     * @param string $condition
     * @param string|null $operator
     * @return $this
     */
    public function having(string $condition, string $operator = null): self
    {
        $this->having['data'][] = [
            'condition' => $condition,
            'operator' => $operator
        ];
        return $this;
    }

    public function initHaving(): void
    {
        $this->having = ['data' => []];
    }

    /**
     * @param int $limit
     * @param int|null $offset
     * @return $this
     */
    public function limit(int $limit, int $offset = null): self
    {
        $this->limit = [
            'offset' => $offset,
            'limit' => $limit
        ];
        return $this;
    }

    public function initLimit(): void
    {
        $this->limit = null;
    }

    /**
     * @param string $column
     * @return string
     */
    public function getColumnName(string $column): string
    {
        $datas = explode('.', $column);
        return end($datas);
    }

    /**
     * @return string
     */
    public function getRawQuery(): string
    {
        if (!$this->rawQuery) {
            $this->buildRawQuery();
        }

        return $this->rawQuery;
    }

    /**
     * @param bool $isDebug
     * @return string
     */
    public function getCacheKeyWithRawQuery(bool $isDebug = false): string
    {
        return !$isDebug ? 'query:'.hash('md5', $this->getRawQuery()) :$this->getRawQuery();
    }

    private function buildRawQuery(): void
    {
        $this->rawQuery = $this->query;
        foreach ($this->values as $value) {
            /** @var Parameter $value */
            $this->rawQuery = str_replace($value->getBindName(), $value->getValueForLog(), $this->rawQuery);
        }
    }
}