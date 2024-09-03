<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Oracle;

use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CProcedureParameterMode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CProcedureParameterType;

class Parameter
{
    private $index;
    private $mode;
    private $type;
    private $name;
    private $length;
    private $bindName;
    public $value;

    /**
     * Parameter constructor.
     * @param int $index
     * @param string|null $mode
     * @param null $value
     * @param string|null $type
     * @param string|null $name
     * @param int|null $length
     * @param string|null $bindName
     */
    public function __construct(int $index, string $mode = null, $value = null, string $type = null, string $name = null, int $length = null, string $bindName = null)
    {
        $this->index = $index;
        $this->mode = $mode;
        $this->value = $value;
        $this->type = $type ?? $this->initType($value);
        $this->name = $this->checkSpecialCharacter($name);
        $this->length = $length;
        $this->bindName = $bindName ?? $this->makeBindName($index);
    }

    /**
     * @return int
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @return string|null
     */
    public function getMode(): ?string
    {
        return $this->mode;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->convertType();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->length === null || $this->isCursor() === true ?-1 :$this->length;
    }

    /**
     * @return string
     */
    public function getBindName(): string
    {
        return $this->bindName;
    }

    /**
     * @return bool
     */
    public function isOutParameter(): bool
    {
        if ($this->getMode() === null) {
            false;
        }

        return in_array($this->getMode(), [CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_OUT, CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_INOUT], true) && !$this->isCursor();
    }

    /**
     * @return bool
     */
    public function isCursor(): bool
    {
        return $this->type === CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_CURSOR;
    }

    /**
     * @param int $index
     * @return string
     */
    private function makeBindName(int $index): string
    {
        return ':p'.$index;
    }

    /**
     * @param string|null $name
     * @return string
     */
    private function checkSpecialCharacter(string $name = null): string
    {
        if ($name === null) {
            return '';
        }

        if (preg_match('/[\'^£$%&*()}{@#~?><>,|=+¬-]/', $name)) {
            throw new OracleStorageException('Invalid ouput parameter name: Special characters not supported: '.$name);
        }

        return $name;
    }

    /**
     * @return int
     */
    private function convertType(): int
    {
        switch ($this->type) {
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_INTEGER:
                return SQLT_INT;
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_CURSOR:
                return OCI_B_CURSOR;
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_BOOLEAN:
                return SQLT_BOL;
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_BLOB:
                return SQLT_BLOB;
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_CLOB:
                return SQLT_CLOB;
            default:
                return SQLT_CHR;
        }
    }

    /**
     * @param $value
     * @return string
     */
    private function initType($value): string
    {
        $type = gettype($value);
        switch ($type) {
            case 'string':
            case 'NULL':
            case 'double':
                return CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_STRING;
            case 'integer':
                return CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_INTEGER;
            case 'boolean':
                return CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_BOOLEAN;
            default:
                throw new OracleStorageException('Invalid parameter type: '.$type);
        }
    }
}