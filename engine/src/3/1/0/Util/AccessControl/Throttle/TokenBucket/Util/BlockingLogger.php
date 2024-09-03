<?php
namespace Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Util;

use Exception;
use Ntuple\Synctree\Log\CreateLogger;

class BlockingLogger extends CreateLogger
{
    private $config;

    /**
     * @param array $config
     * @throws Exception
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        parent::__construct($config);
    }

    /**
     * @return string
     */
    protected function makeLogFileName(): string
    {
        return $this->config['path'] . $this->config['filename'] . '.log';
    }
}