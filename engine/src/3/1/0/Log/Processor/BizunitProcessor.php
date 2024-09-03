<?php
namespace Ntuple\Synctree\Log\Processor;

use Ntuple\Synctree\Plan\Unit\TransactionManager;

class BizunitProcessor
{
    private $transactionManager;

    /**
     * BizunitProcessor constructor.
     * @param TransactionManager $transactionManager
     */
    public function __construct(TransactionManager $transactionManager)
    {
        $this->transactionManager = $transactionManager;
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $record['extra']['bizunit_id'] = $this->transactionManager->getBizunitID();
        $record['extra']['bizunit_version'] = $this->transactionManager->getBizunitVersion();
        $record['extra']['revision_id'] = $this->transactionManager->getRevisionID();
        $record['extra']['environment'] = $this->transactionManager->getEnvironment();
        $record['extra']['transaction_key'] = $this->transactionManager->getTransactionKey();

        return $record;
    }
}