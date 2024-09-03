<?php
namespace Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket;

use Exception;
use malkusch\lock\exception\MutexException;
use Ntuple\Synctree\Util\AccessControl\Exception\LimitExceededException;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Exception\InvalidArgumentException;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Exception\StorageException;
use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Util\TokenConverter;

class TokenBucket
{
    private $rate;
    private $capacity;
    private $storage;
    private $tokenConverter;
    private $status;

    /**
     * @param RedisStorage $storage
     * @param Rate $rate
     * @param int|null $capacity
     */
    public function __construct(RedisStorage $storage, Rate $rate, int $capacity = null)
    {
        if ($capacity <= 0) {
            throw new InvalidArgumentException('Capacity should be greater than 0.');
        }

        $this->capacity = $capacity;
        $this->rate = $rate;
        $this->storage = $storage;
        $this->status = new Status($this->storage->getKey(), $this->rate->getLimit());
        $this->tokenConverter = new TokenConverter($rate);
    }

    /**
     * @param int $tokens
     * @throws Exception
     */
    public function bootstrap(int $tokens = 0): void
    {
        try {
            if ($tokens > $this->capacity) {
                throw new InvalidArgumentException('Initial token amount ('.$tokens.') is larger than the capacity ('.$this->capacity.').');
            }
            if ($tokens < 0) {
                throw new InvalidArgumentException('Initial token amount ('.$tokens.') should be greater than 0.');
            }

            $this->storage->getMutex()
                ->check(function () {
                    return !$this->storage->isBootstrapped();
                })
                ->then(function () use ($tokens) {
                    $this->storage->bootstrap($this->tokenConverter->convertTokensToMicrotime($tokens));
                });
        } catch (MutexException $e) {
            throw new StorageException('Could not lock bootstrapping', 0, $e);
        }
    }

    /**
     * @param int $tokens
     * @return Status
     * @throws Exception
     */
    public function consume(int $tokens = 1): Status
    {
        try {
            if ($tokens > $this->capacity) {
                throw new InvalidArgumentException('Token amount ('.$tokens.') is larger than the capacity ('.$this->capacity.').');
            }
            if ($tokens <= 0) {
                throw new InvalidArgumentException('Token amount ('.$tokens.') should be greater than 0.');
            }

            $synchronized = $this->storage->getMutex()->synchronized(
                function () use ($tokens) {
                    $tokensAndMicrotime = $this->loadTokensAndTimestamp();
                    $microtime = $tokensAndMicrotime['microtime'];
                    $availableTokens = $tokensAndMicrotime['tokens'];
                    $this->status->setRemainingCount($availableTokens);

                    $delta = $availableTokens - $tokens;
                    if ($delta < 0) {
                        $passed  = microtime(true) - $microtime;
                        $seconds = max(0, $this->tokenConverter->convertTokensToSeconds($tokens) - $passed);
                        $this->status->setRemainingAttempts(floor($seconds));
                        return false;
                    }

                    $microtime += $this->tokenConverter->convertTokensToSeconds($tokens);
                    $this->storage->setMicrotime($microtime);
                    return true;
                }
            );

            if (!$synchronized) {
                throw LimitExceededException::for($this->status);
            }

            return $this->status;
        } catch (MutexException $e) {
            throw new StorageException('Could not lock token consumption.', 0, $e);
        }
    }

    /**
     * @return Rate
     */
    public function getRate(): Rate
    {
        return $this->rate;
    }

    /**
     * @return mixed
     */
    public function getTokens()
    {
        return $this->loadTokensAndTimestamp()['tokens'];
    }

    /**
     * @return array
     */
    private function loadTokensAndTimestamp(): array
    {
        try {
            $microtime = $this->storage->getMicrotime();

            // Drop overflowing tokens
            $minMicrotime = $this->tokenConverter->convertTokensToMicrotime($this->capacity);
            if ($minMicrotime > $microtime) {
                $microtime = $minMicrotime;
            }

            $tokens = $this->tokenConverter->convertMicrotimeToTokens($microtime);
            return [
                'tokens' => $tokens,
                'microtime' => $microtime
            ];
        } catch (Exception $ex) {
            throw new StorageException($ex->getMessage());
        }
    }
}