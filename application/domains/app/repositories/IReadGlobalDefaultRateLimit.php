<?php
namespace domains\app\repositories;

use Ntuple\Synctree\Util\AccessControl\RateLimit\Rate;

interface IReadGlobalDefaultRateLimit
{
    public function read(): Rate;
}