<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver\Mssql;

use Ntuple\Synctree\Exceptions\Contexts\InvalidArgumentExceptionContext;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CProcedureParameterMode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CProcedureParameterType;
use Ntuple\Synctree\Util\Storage\Driver\Postgres\PostgresStorageException;
use PDO;

/**
 * @since SRT-140
 */
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
     *
     * @param int $index
     * @param string|null $mode
     * @param null $value
     * @param string|null $type
     * @param string|null $name
     * @param int|null $length
     * @param string|null $bindName
     */
    public function __construct(
        int $index,
        string $mode = null,
        $value = null,
        string $type = null,
        string $name = null,
        int $length = null,
        string $bindName = null)
    {
        $this->index = $index;
        $this->mode = $mode;
        $this->type = $type ?? $this->initType($value);
        $this->value = $this->castType($value);
        $this->name = $this->checkSpecialCharacter($name);
        $this->length = $length;
        $this->bindName = $bindName ?? $this->makeBindName($name);
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
        $type = $this->convertType();
        if ($this->mode === CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_INOUT) {
            return $type|PDO::PARAM_INPUT_OUTPUT;
        }

        return $type;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * MSSQL 프로시저의 경우, In mode param에 0이 아닌 값을 사용하면 inout param으로 인식한다.
     *
     * @return int
     */
    public function getLength(): int
    {
        return $this->length ?? 0;
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
    public function isInParameter(): bool
    {
        return $this->mode === CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_IN;
    }

    /**
     * @return bool
     */
    public function isOutParameter(): bool
    {
        return in_array($this->mode, [
            CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_OUT,
            CProcedureParameterMode::PROCEDURE_PARAMETER_MODE_INOUT
        ], true);
    }

    /**
     * @param string|null $name
     * @return string
     */
    private function makeBindName(string $name = null): string
    {
        return $name === null ? '' :':'.$name;
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
            throw (new PostgresStorageException('Invalid output parameter name: Special characters not supported.'))
                ->setContext((new InvalidArgumentExceptionContext())
                    ->setExpected('[\\w]+')
                    ->setActual($name)
                );
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
                return PDO::PARAM_INT;
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_CURSOR:
                return PDO::PARAM_STMT;
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_BOOLEAN:
                return PDO::PARAM_BOOL;
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_CLOB:
                return PDO::PARAM_LOB;
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_NULL:
                return PDO::PARAM_NULL;
            default:
                return PDO::PARAM_STR;
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
            case 'double':
            case 'string':
                return CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_STRING;
            case 'NULL':
                return CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_NULL;
            case 'integer':
                return CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_INTEGER;
            case 'boolean':
                return CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_BOOLEAN;
            default:
                throw (new PostgresStorageException('Invalid parameter type: '.$type))
                    ->setContext((new InvalidArgumentExceptionContext())
                        ->setExpected('string|integer|double|boolean|null')
                        ->setActual($type)
                    );
        }
    }


    /**
     * `MSSQL`의 경우, `INOUT param`에 대해서 타입이 맞지 않으면 {@link \PDOException}이 발생한다.
     * block 입력은 대개 string 이므로 parameter 타입에 맞춰 정확히 casting 해 둔다.
     *
     * @param $value
     * @return bool|int|mixed|null
     */
    private function castType($value)
    {
        switch ($this->type) {
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_INTEGER:
                return (int) $value;
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_BOOLEAN:
                return (bool) $value;
            case CProcedureParameterType::PROCEDURE_PARAMETER_TYPE_NULL:
                return null;
            default:
                return $value;
        }
    }
}