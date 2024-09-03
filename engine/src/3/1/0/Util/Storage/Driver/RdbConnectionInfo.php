<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver;

use DateTimeZone;
use Ntuple\Synctree\Util\Storage\Exception\UnsupportedOperationException;


/**
 * @since SYN-389
 */
abstract class RdbConnectionInfo
{
    /** @var string */
    private $driver;

    /** @var string */
    private $host;

    /** @var integer */
    private $port;

    /** @var string */
    private $username;

    /** @var string */
    private $password;

    /** @var string */
    private $database;

    /** @var DateTimeZone */
    private $timezone;

    /** @var string */
    private $charset;

    /** @var array Key-Value associative array */
    protected $options;

    /** @var RdbSslOption */
    private $sslOptions;


    /**
     * @param string $driver
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     * @param string|null $database
     * @param DateTimeZone|string|null $timezone default: depends on server setup
     * @param string $charset default 'utf8'
     * @param array $options default []
     * @param RdbSslOption|null $sslOptions
     */
    public function __construct(
        string $driver, string $host, int $port,
        string $username, string $password,
        ?string $database = null,
        $timezone = null,
        string $charset='utf8',
        array $options=[],
        ?RdbSslOption $sslOptions=null
    ) {

        $this->driver = $driver;
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;

        if (!empty($timezone)) {
            if ($timezone instanceof DateTimeZone) {
                $this->timezone = $timezone;
            } else {
                $this->timezone = new DateTimeZone($timezone);
            }
        } else {
            $this->timezone = new DateTimeZone('');
        }

        $this->charset = $charset ?? 'utf8';
        $this->options = $options ?? [];

        $this->sslOptions = $sslOptions;
    }

    function getDriver(): string
    {
        return $this->driver;
    }

    function getHost(): string
    {
        return $this->host;
    }

    function getUsername(): string
    {
        return $this->username;
    }

    function getPassword(): string
    {
        return $this->password;
    }

    function getDatabaseName(): string
    {
        return $this->database;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function getSSLOptions(): RdbSslOption
    {
        return $this->sslOptions;
    }

    /**
     * @return array Key-Value associative array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return bool SSL mode 값이 명시되면 참
     * @since SRT-10
     */
    public function isSSLEnabled(): bool
    {
        return !empty($this->sslOptions) && (
            $this->sslOptions->getMode() !== RdbSslMode::DISABLED);
    }

    /**
     * 지정된 SSL mode 반환.
     *
     * @return string 기본값: {@link RdbSslMode::VERIFY_CA}
     * @since SRT-10
     */
    public function getSSLMode(): string
    {
        if (!$this->isSSLEnabled()) {
            throw new UnsupportedOperationException('SSL is unavailable');
        }
        return $this->sslOptions->getMode();
    }
}