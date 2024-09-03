<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Oracle\Oci8;

use Throwable;

class Oci8Connect extends Oci8ErrorHandler
{
    private $connection;
    private $options;

    /**
     * 이 값은 `studio`와 동일해야 함
     *
     * @since SRT-173
     */
    public const OPTION_PROPERTY_PCONNECT = 'pconnect';

    /**
     * Oci8Connect constructor.
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param string $charset
     * @param array $options
     * @param bool $isPconnectDisabled @since SRT-173
     */
    public function __construct(
        string $dsn, string $username, string $password, string $charset, array $options = [],
        bool $isPconnectDisabled = false)
    {
        $this->options = $options;

        if (true === ($options[self::OPTION_PROPERTY_PCONNECT] ?? false) && false === $isPconnectDisabled) {
            $this->connectPersistently($dsn, $username, $password, $charset);
        } else {
            $this->connect($dsn, $username, $password, $charset);
        }
    }

    /**
     * @param string $query
     * @return Oci8Statement
     * @throw Oci8Exception
     */
    public function prepare(string $query): Oci8Statement
    {
        try {
            $statement = oci_parse($this->connection, $query);
            if (!$statement) {
                $e = oci_error($this->connection);
                throw new Oci8Exception($e['message']);
            }

            return new Oci8Statement($this, $statement, $this->options);
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_parse/']);
            throw new Oci8Exception('Failed to parse');
        }
    }

    /**
     * @return Oci8Cursor
     * @throw Oci8Exception
     */
    public function getNewCursor(): Oci8Cursor
    {
        try {
            $cursor = oci_new_cursor($this->connection);
            if (!$cursor) {
                $e = oci_error($this->connection);
                throw new Oci8Exception($e['message']);
            }

            return new Oci8Cursor($this, $cursor);
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_new_cursor/']);
            throw new Oci8Exception('Failed to get cursor');
        }
    }

    public function commit(): void
    {
        try {
            if (!oci_commit($this->connection)) {
                $e = oci_error($this->connection);
                throw new Oci8Exception($e['message']);
            }
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_commit/']);
            throw new Oci8Exception('Failed to commit');
        }
    }

    public function rollback(): void
    {
        try {
            if (!oci_rollback($this->connection)) {
                $e = oci_error($this->connection);
                throw new Oci8Exception($e['message']);
            }
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_rollback/']);
            throw new Oci8Exception('Failed to rollback');
        }
    }

    public function close(): void
    {
        try {
            if ($this->connection) {
                oci_close($this->connection);
                $this->connection = null;
            }
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_close/']);
            throw new Oci8Exception('Failed to close');
        }
    }

    /**
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param string $charset
     * @throw Oci8Exception
     */
    private function connect(string $dsn, string $username, string $password, string $charset): void
    {
        try {
            $this->connection = oci_connect($username, $password, $dsn, $charset);
            if (!$this->connection) {
                $e = oci_error();
                throw new Oci8Exception($e['message'], $e['code']);
            }
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_connect/']);
            throw new Oci8Exception('Failed to connect');
        }
    }

    /**
     * 지속성 연결을 생성. `oci8.persistent_timeout`설정에 유의.
     *
     * @since SRT-173
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param string $charset
     */
    private function connectPersistently(string $dsn, string $username, string $password, string $charset): void
    {
        try {
            $this->connection = oci_pconnect($username, $password, $dsn, $charset);
            if (!$this->connection) {
                $e = oci_error();
                throw new Oci8Exception($e['message'], $e['code']);
            }
        } catch (Throwable $ex) {
            $this->errorHandler($ex->getMessage(), ['/^oci_connect/']);
            throw new Oci8Exception('Failed to connect', 0, $ex);
        }
    }
}