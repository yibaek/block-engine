<?php
namespace Ntuple\Synctree\Log\Processor;

/**
 * QueryProcessor
 * 실행 sql 관련 extra data 세팅을 위한 processor (bindings:바인딩, excute:실행정보)
 * 
 * @since SYN-397
 */
class QueryProcessor
{
    private $queryType;
    private $query;
    private $bindings;
    private $executeInfo;

    /**
     * QueryProcessor constructor.
     *
     * @param string $queryType : SELECT, INSERT, DELETE, UPDATE
     * @param string $query : sql string
     * @param array|null $bindings
     * @param array $executeInfo
     */
    public function __construct(string $queryType, string $query, ?array $bindings, array $executeInfo = [])
    {
        $this->queryType = $queryType;
        $this->query = $query;
        $this->bindings = $bindings;
        $this->executeInfo = $executeInfo;
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record): array
    {
        if (!empty($this->executeInfo)) {
            $record['extra']['query']['execute'] = [
                'class' => sprintf('%s', ($this->executeInfo['class'] ?? 'undefined')),
                'function' => sprintf('%s', ($this->executeInfo['function'] ?? 'undefined')),
                'line' => sprintf('%s', ($this->executeInfo['line'] ?? 'undefined')),
            ];
        }

        // select, delete 의 경우 binding 정보 함께 기록
        if (in_array($this->queryType, ['SELECT', 'DELETE']) && !empty($this->bindings)) {
            $record['extra']['query']['bindings'] = $this->bindings;
        }

        return $record;
    }
}