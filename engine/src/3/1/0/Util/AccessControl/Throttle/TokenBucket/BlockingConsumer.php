<?php
namespace Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Util\AccessControl\Exception\LimitExceededException;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Exception\InvalidArgumentException;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Exception\TimeoutException;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Util\BlockingLogMessage;

class BlockingConsumer
{
    private $storage;
    private $bucket;
    private $timeout;

    /**
     * @param PlanStorage $storage
     * @param TokenBucket $bucket
     * @param int|null $timeout
     */
    public function __construct(PlanStorage $storage, TokenBucket $bucket, int $timeout = null)
    {
        $this->storage = $storage;
        $this->bucket = $bucket;

        if ($timeout < 0) {
            throw new InvalidArgumentException('Timeout must be null or positive');
        }
        $this->timeout = $timeout;
    }

    /**
     * @param int $tokens
     * @return Status
     * @throws Exception
     */
    public function consume(int $tokens = 1): Status
    {
        try {
            $status = $this->bucket->consume($tokens);

            // logging for monitoring
            (new BlockingLogMessage($this->storage, $status))->loggingForMonitoring();

            return $status;
        } catch (LimitExceededException $ex) {
            $status = $ex->getStatus();
            $timedout = $this->getTimedOut();

            $this->throwTimeoutIfExceeded($timedout);
            $seconds = $this->keepSecondsWithinTimeout($status->getRemainingAttempts(), $timedout);

            // logging for monitoring
            (new BlockingLogMessage($this->storage, $status))->loggingForMonitoring();

            if ($seconds > 1) {
                $sleepSeconds = ((int) $seconds) - 1;

                sleep($sleepSeconds);
                $seconds -= $sleepSeconds;
            }

            // sleep at least 1 millisecond.
            usleep(max(1000, $seconds * 1000000));

            return $this->consume($tokens);
        }
    }

    /**
     * @param float|null $timedOut
     * @throws TimeoutException
     */
    private function throwTimeoutIfExceeded(?float $timedOut): void
    {
        if (is_null($timedOut)) {
            return;
        }
        if (time() >= $timedOut) {
            throw new TimeoutException('Timed out');
        }
    }

    /**
     * @param float $seconds
     * @param float|null $timedOut
     * @return float|mixed
     */
    private function keepSecondsWithinTimeout(float $seconds, ?float $timedOut)
    {
        if (is_null($timedOut)) {
            return $seconds;
        }

        $remainingSeconds = max($timedOut - microtime(true), 0);
        return min($remainingSeconds, $seconds);
    }

    /**
     * @return float|null
     */
    private function getTimedOut(): ?float
    {
        return is_null($this->timeout) ? null : (microtime(true) + $this->timeout);
    }
}