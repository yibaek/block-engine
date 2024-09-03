<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\Parameter;

class CParameter
{
    private $key;
    private $value;
    private $datatype;
    private $required;
    private $description;

    /**
     * CParameter constructor.
     * @param string|int|null $key
     * @param mixed $value
     * @param string|null $datatype
     * @param bool|null $required
     * @param string|null $description
     */
    public function __construct($key = null, $value = null, string $datatype = null, bool $required = null, string $description = null)
    {
        $this->key = $key;
        $this->value = $value;
        $this->datatype = $datatype;
        $this->required = $required;
        $this->description = $description;
    }

    /**
     * @return string|int|null
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return mixed|null
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string|null
     */
    public function getDataType(): ?string
    {
        return $this->datatype;
    }

    /**
     * @return string|null
     */
    public function getRequired(): ?string
    {
        return $this->required;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }
}