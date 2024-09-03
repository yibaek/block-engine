<?php
namespace domains\app\repositories\implementations;

use Ntuple\Synctree\Util\AccessControl\RateLimit\Rate;
use domains\app\repositories\IReadGlobalDefaultRateLimit;

class ReadGlobalDefaultRateLimit implements IReadGlobalDefaultRateLimit
{
    public function read(): Rate
    {
        $rate = new Rate;
        $rate->perCustom(10, 1);
        return $rate;
    }
}