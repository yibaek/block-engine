<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver;


/**
 * RDB connection SSL options VO
 *
 * @since SRT-10
 */
class RdbSslOption
{
    /** @var string */
    public const SSL_MODE = 'ssl_mode';

    /** @var string CA cert path */
    public const SSL_CA_CERT = 'storage_ssl_ca_path';

    /** @var string Client key path */
    public const SSL_CLIENT_KEY = 'storage_ssl_key_path';

    /** @var string Client cert path */
    public const SSL_CLIENT_CERT = 'storage_ssl_cert_path';

    private $mode;
    private $caCertPath;
    private $clientCertPath;
    private $clientKeyPath;

    /**
     * @param string|null $caPath
     * @param string|null $clientCertPath
     * @param string|null $clientKeyPath
     * @param string $mode {@link RdbSslMode}
     */
    public function __construct(
        ?string $caPath = null,
        ?string $clientCertPath = null,
        ?string $clientKeyPath = null,
        string $mode = RdbSslMode::PREFER)
    {
        $this->caCertPath = $caPath;
        $this->clientCertPath = $clientCertPath;
        $this->clientKeyPath = $clientKeyPath;
        $this->mode = $mode;
    }

    /**
     * Factory method.
     *
     * @param array $config [{@link RdbSslOption::SSL_}* => '...']
     * @return RdbSslOption
     */
    public static function fromArray(array $config): RdbSslOption
    {
        if (!empty($config[self::SSL_CA_CERT])) {
            $config[self::SSL_MODE] = RdbSslMode::VERIFY_CA;
        }

        return new self(
            @$config[self::SSL_CA_CERT],
            @$config[self::SSL_CLIENT_CERT],
            @$config[self::SSL_CLIENT_KEY],
            $config[self::SSL_MODE] ?? RdbSslMode::PREFER
        );
    }

    /**
     * @return string {@link RdbSslMode} 값 중 하나.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @return string|null CA 인증서 경로
     */
    public function getCACertPath(): ?string
    {
        return $this->caCertPath;
    }

    /**
     * @return string|null client cert 경로
     */
    public function getClientCertPath(): ?string
    {
        return $this->clientCertPath;
    }

    /**
     * @return string|null client private key 경로
     */
    public function getClientKeyPath(): ?string
    {
        return $this->clientKeyPath;
    }

    /** @return bool client cert set properly */
    public function hasClientCert(): bool
    {
        return !empty($this->clientKeyPath) && !empty($this->clientCertPath);
    }
}