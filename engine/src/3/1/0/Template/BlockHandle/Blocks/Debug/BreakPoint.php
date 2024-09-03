<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Debug;

use Exception;
use libraries\constant\CommonConst;
use Ntuple\Synctree\Exceptions\Inner\DebugBreakPointException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\RedisUtil;
use Throwable;

class BreakPoint implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'debug';
    public const ACTION = 'break-point';

    private $storage;
    private $type;
    private $action;
    private $isDebugMode;
    private $key;
    private $step;
    private $expiryTime;
    private $isTarget;

    /**
     * BreakPoint constructor.
     * @param PlanStorage $storage
     * @param bool $isDebugMode
     * @param string|null $key
     * @param int $step
     * @param int|null $expiryTime
     * @param bool $isTarget
     */
    public function __construct(PlanStorage $storage, bool $isDebugMode = false, string $key = null, int $step = null, int $expiryTime = null, bool $isTarget = false)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->isDebugMode = $isDebugMode;
        $this->key = $key;
        $this->step = $step;
        $this->expiryTime = $expiryTime;
        $this->isTarget = $isTarget;
    }

    /**
     * @param array $data
     * @return IBlock
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->isDebugMode = $data['template']['is-debugmode'];
        $this->key = $data['template']['key'];
        $this->step = $data['template']['step'];
        $this->expiryTime = $data['template']['expiry-time'];
        $this->isTarget = $data['template']['is-target'];

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'template' => [
                'is-debugmode' => $this->isDebugMode,
                'key' => $this->key,
                'step' => $this->step,
                'expiry-time' => $this->expiryTime,
                'is-target' => $this->isTarget
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @throws Throwable
     */
    public function do(array &$blockStorage): void
    {
        if (!$this->isDebugMode()) {
            return;
        }

        // set debug data
        if ($this->isTarget) {
            $this->setDebugData($blockStorage);
            throw new DebugBreakPointException(200,
                ['Content-Type' => 'application/json'],
                [
                    'current-step' => $this->step,
                    'debug-data' => $blockStorage
                ]);
        }

        // restore debug data
        $this->restoreData($blockStorage);
    }

    /**
     * @param array $blockStorage
     * @throws Throwable
     */
    private function setDebugData(array $blockStorage): void
    {
        $debugData = $this->getDebugData();
        $debugData[$this->step] = $blockStorage;
        RedisUtil::setDataWithExpire($this->storage->getRedisResource(), $this->key, CommonConst::DEBUG_REDIS_DB, $this->getDebugDataExpire(), $debugData);
    }

    /**
     * @param array $blockStorage
     * @throws Throwable
     */
    private function restoreData(array &$blockStorage): void
    {
        $debugData = $this->getDebugData();
        if (!empty($debugData)) {
            $blockStorage = $debugData[$this->step];
        }
    }

    /**
     * @return array
     * @throws Throwable
     */
    private function getDebugData(): array
    {
        if (false === ($resData=RedisUtil::getData($this->storage->getRedisResource(), $this->key, CommonConst::REDIS_DEBUG_SESSION))) {
            return [];
        }

        return $resData;
    }

    /**
     * @return int
     */
    private function getDebugDataExpire(): int
    {
        return $this->expiryTime - time();
    }

    /**
     * @return bool
     */
    private function isDebugMode(): bool
    {
        $header = $this->storage->getOrigin()->getHeaders();
        $header[strtoupper(CommonConst::SYNCTREE_PLAN_TEST_MODE)];

        return defined('PLAN_MODE')
            && PLAN_MODE === 'testing'
            && $header[strtoupper(CommonConst::SYNCTREE_PLAN_TEST_MODE)] === 'debug'
            && $this->isDebugMode;
    }
}