<?php
namespace libraries\log\processor;

class TransactionProcessor
{
    /**
     * TransactionProcessor constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record): array
    {
        $record['extra']['transaction_key'] = defined('TRANSACTION_KEY') ?TRANSACTION_KEY: 'undefined';

        return $record;
    }
}