<?php
namespace Ntuple\Synctree\Exceptions\Inner;

class LoopContinueException extends SynctreeInnerException
{
    /**
     * LoopContinueException constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}