<?php
namespace Ntuple\Synctree\Syrn\Log;

use Exception;
use Monolog\Logger;

class CreateLogger extends \Ntuple\Synctree\Log\CreateLogger
{
    private $config;

    /**
     * CreateLogger constructor.
     * @param array|null $config
     * @throws Exception
     */
    public function __construct(array $config = null)
    {
        $this->config = $this->getLogConfig();

        parent::__construct($this->config);
    }

    /**
     * @return string
     */
    protected function makeLogFileName(): string
    {
        $postfix = $this->config['postfix'] ?? 'syrn';
        return $this->config['path'] . $postfix . '-' . date('Ymd') . '.log';
    }

    /**
     * @return int|null
     */
    protected function getFilePermission(): ?int
    {
        return 0777;
    }

    /**
     * @return array
     */
    private function getLogConfig(): array
    {
        if (null !== ($config=$this->getLoggerConfig())) {
            return $config;
        }

        return [
            'name' => 'syrn',
            'postfix' => 'syrn',
            'path' => BASE_DIR . '/logs/',
            'level' => Logger::DEBUG
        ];
    }

    /**
     * @return array|null
     */
    private function getLoggerConfig(): ?array
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        return $config['settings']['syrn']['logger'] ?? null;
    }
}
