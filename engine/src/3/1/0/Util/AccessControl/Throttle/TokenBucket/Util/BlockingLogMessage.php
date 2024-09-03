<?php
namespace Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Util;

use Exception;
use libraries\constant\CommonConst;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Plan\Unit\ProxyManager;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Status;
use Ntuple\Synctree\Util\CommonUtil;

class BlockingLogMessage
{
    private $storage;
    private $status;

    /**
     * @param PlanStorage $storage
     * @param Status $status
     * @throws Exception
     */
    public function __construct(PlanStorage $storage, Status $status)
    {
        $this->storage = $storage;
        $this->status = $status;
    }

    public function loggingForMonitoring(): void
    {
        try {
            if (($proxyManager=$this->storage->getProxyManager()) != null) {
                if (($config=$this->loadConfig()) != false) {
                    (new BlockingLogger($config['logger']))->getLogger()->info($this->makeLoggingMessage($proxyManager));
                }
            }
            return;
        } catch (Exception $ex) {
            return;
        }
    }

    /**
     * @return array|false
     */
    private function loadConfig()
    {
        return CommonUtil::readUserConfig(CommonConst::PATH_THROTTLE_BUFFER_LOG_CONFIG_PATH);
    }

    /**
     * @param ProxyManager $proxyManager
     * @return string
     * @throws \JsonException
     */
    private function makeLoggingMessage(ProxyManager $proxyManager): string
    {
        $addData = [
            'transaction_key' => $this->storage->getTransactionManager()->getTransactionKey()
        ];

        return json_encode(array_merge($proxyManager->getLogMessage(), $this->status->getData(), $addData), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}