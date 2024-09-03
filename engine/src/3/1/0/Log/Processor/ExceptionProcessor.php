<?php
namespace Ntuple\Synctree\Log\Processor;

use Throwable;

/**
 * ExceptionProcessor
 * Exception, Error 관련된 로깅을 위한 Processor
 * 
 * @since SYN-398
 */
class ExceptionProcessor
{
    private $ex;

    /**
     * ExceptionProcessor constructor.
     *
     * @param Throwable $ex
     */
    public function __construct(Throwable $ex)
    {
        $this->ex = $ex;
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record): array
    {
        // generates extra data
        $record['extra']['error'] = [
            'exception' => get_class($this->ex),
            'file' => $this->ex->getFile(),
            'line' => $this->ex->getLine()
        ];

        // reference data 를 context -> extra 로 이동 (비검색 데이터)
        if (isset($record['context']['reference'])) {
            $record['extra']['error']['reference'] = $record['context']['reference'];
            unset($record['context']['reference']);
        }
        
        // set previous exception data
        $previous = $this->ex->getPrevious();
        if ($previous instanceof Throwable) {
            $record['extra']['error']['previous'] = [
                'exception' => get_class($previous),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
                'message' => $previous->getMessage()
            ];
        }

        return $record;
    }
}