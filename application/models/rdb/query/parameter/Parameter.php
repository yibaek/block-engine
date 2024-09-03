<?php
namespace models\rdb\query\parameter;

use PDO;

class Parameter
{
    public $value;
    private $column;
    private $index;
    private $type;

    /**
     * Parameter constructor.
     * @param string $column
     * @param $value
     */
    public function __construct(string $column, $value)
    {
        $this->column = $column;
        $this->setValue($value);
    }

    /**
     * @param int $index
     * @return $this
     */
    public function setIndex(int $index): self
    {
        $this->index = $index;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setValue($value): self
    {
        $this->value = $value;
        return $this->setType();
    }

    /**
     * @return $this
     */
    public function setType(): self
    {
        $this->type = gettype($this->value);
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @return string
     */
    public function getBindName(): string
    {
        return $this->makeBindName();
    }

    /**
     * @return int
     */
    public function getBindType(): int
    {
        switch ($this->getType()) {
            case 'integer':
                return PDO::PARAM_INT;
            case 'boolean':
                return PDO::PARAM_BOOL;
            case 'NULL':
                return PDO::PARAM_NULL;
            default:
                return PDO::PARAM_STR;
        }
    }

    /**
     * @return mixed
     */
    public function getValueForLog()
    {
        return $this->getType() === 'string' ? "'$this->value'" : $this->value;
    }

    /**
     * @return string
     */
    private function makeBindName(): string
    {
        return ':'.$this->column;
    }
}