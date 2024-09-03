<?php
namespace Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Util;

use Ntuple\Synctree\Util\AccessControl\Throttle\TokenBucket\Rate;

class TokenConverter
{
    private $rate;
    private $bcScale = 8;

    /**
     * TokenConverter constructor.
     * @param Rate $rate
     */
    public function __construct(Rate $rate)
    {
        $this->rate = $rate;
    }

    /**
     * @param float $seconds
     * @return int
     */
    public function convertSecondsToTokens(float $seconds): int
    {
        return (int) ($seconds * $this->rate->getTokensPerSecond());
    }

    /**
     * @param int $tokens
     * @return float
     */
    public function convertTokensToSeconds(int $tokens): float
    {
        return $tokens / $this->rate->getTokensPerSecond();
    }

    /**
     * @param int $tokens
     * @return float
     */
    public function convertTokensToMicrotime(int $tokens): float
    {
        return microtime(true) - $this->convertTokensToSeconds($tokens);
    }

    /**
     * @param float $microtime
     * @return int
     */
    public function convertMicrotimeToTokens(float $microtime): int
    {
        $delta = bcsub(microtime(true), $microtime, $this->bcScale);
        return $this->convertSecondsToTokens($delta);
    }
}
