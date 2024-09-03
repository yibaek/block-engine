<?php
namespace Ntuple\Synctree\Exceptions\Inner;

class LoopBreakException extends SynctreeInnerException
{
    /**
     * LoopBreakException constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}