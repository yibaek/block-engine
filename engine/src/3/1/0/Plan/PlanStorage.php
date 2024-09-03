<?php declare(strict_types=1);
namespace Ntuple\Synctree\Plan;

use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Models\Rdb\IRdbMgr;
use Ntuple\Synctree\Plan\Stack\StackManager;
use Ntuple\Synctree\Plan\Unit\AccessControler;
use Ntuple\Synctree\Plan\Unit\AccountManager;
use Ntuple\Synctree\Plan\Unit\AuthDataManager;
use Ntuple\Synctree\Plan\Unit\DictionaryDataManager;
use Ntuple\Synctree\Plan\Unit\ProductControler;
use Ntuple\Synctree\Plan\Unit\ProxyManager;
use Ntuple\Synctree\Plan\Unit\TransactionManager;
use Ntuple\Synctree\Template\Storage\IOperatorStorage;
use Ntuple\Synctree\Template\Storage\RequestOperator;
use Ntuple\Synctree\Models\Redis\RedisMgr;
use Throwable;

class PlanStorage
{
    public const ORIGIN_DATA_ID = '__origin__';

    private $operators;
    private $accessControler;
    private $transactionManager;
    private $accountManager;
    private $stackManager;
    private $authDataManager;
    private $proxyManager;

    /** @var DictionaryDataManager */
    private $dictionaryDataManager;

    private $productControler;
    private $returnData;
    private $logger;
    private $redis;
    private $rdb;

    /**
     * PlanStorage constructor.
     */
    public function __construct()
    {
        $this->operators = [];
        $this->rdb = [];
    }

    /**
     * @param array $header
     * @param $body
     * @return PlanStorage
     */
    public function setOrigin(array $header, $body): PlanStorage
    {
        $this->addOperatorStorage($this->getOriginID(), new RequestOperator($header, $body));
        return $this;
    }

    /**
     * @return IOperatorStorage
     */
    public function getOrigin(): IOperatorStorage
    {
        return $this->getOperatorStorage($this->getOriginID());
    }

    /**
     * @param string $id
     * @param IOperatorStorage $operator
     * @return PlanStorage
     */
    public function addOperatorStorage(string $id, IOperatorStorage $operator): PlanStorage
    {
        $this->operators[$id] = $operator;
        return $this;
    }

    /**
     * @param string $id
     * @return IOperatorStorage
     */
    public function getOperatorStorage(string $id): ?IOperatorStorage
    {
        return $this->operators[$id] ?? null;
    }

    /**
     * @param IOperatorStorage $responseOperator
     * @return PlanStorage
     */
    public function setReturnData(IOperatorStorage $responseOperator): PlanStorage
    {
        $this->returnData = $responseOperator;
        return $this;
    }

    /**
     * @return IOperatorStorage
     */
    public function getReturnData(): IOperatorStorage
    {
        return $this->returnData;
    }

    /**
     * @return RedisMgr
     */
    public function getRedisResource(): RedisMgr
    {
        return $this->redis;
    }

    /**
     * @param RedisMgr $redis
     * @return PlanStorage
     */
    public function setRedisResource(RedisMgr $redis): self
    {
        $this->redis = $redis;
        return $this;
    }

    /**
     * @return IRdbMgr
     */
    public function getRdbStudioResource(): IRdbMgr
    {
        return $this->rdb['studio'];
    }

    /**
     * @param IRdbMgr $rdb
     * @return PlanStorage
     */
    public function setRdbStudioResource(IRdbMgr $rdb): self
    {
        $this->rdb['studio'] = $rdb;
        return $this;
    }

    /**
     * @return LogMessage
     */
    public function getLogger(): LogMessage
    {
        return $this->logger;
    }

    /**
     * @param LogMessage $logger
     * @return $this
     */
    public function setLogger(LogMessage $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @return AccessControler
     */
    public function getAccessControler(): AccessControler
    {
        return $this->accessControler;
    }

    /**
     * @param AccessControler $controler
     * @return $this
     */
    public function setAccessControler(AccessControler $controler): self
    {
        $this->accessControler = $controler;
        return $this;
    }

    /**
     * @return TransactionManager
     */
    public function getTransactionManager(): TransactionManager
    {
        return $this->transactionManager;
    }

    /**
     * @param TransactionManager $transactionManager
     * @return $this
     */
    public function setTransactionManager(TransactionManager $transactionManager): self
    {
        $this->transactionManager = $transactionManager;
        return $this;
    }

    /**
     * @return AccountManager
     * @throws Throwable
     */
    public function getAccountManager() :AccountManager
    {
        return $this->accountManager;
    }

    /**
     * @param AccountManager $accountManager
     * @return $this
     */
    public function setAccountManager(AccountManager $accountManager): self
    {
        $this->accountManager = $accountManager;
        return $this;
    }

    /**
     * @return ProductControler
     * @throws Throwable
     */
    public function getProductControler() :ProductControler
    {
        return $this->productControler;
    }

    /**
     * @param ProductControler $productControler
     * @return $this
     */
    public function setProductControler(ProductControler $productControler): self
    {
        $this->productControler = $productControler;
        return $this;
    }

    /**
     * @param StackManager $stackManager
     * @return $this
     */
    public function setStackManager(StackManager $stackManager): self
    {
        $this->stackManager = $stackManager;
        return $this;
    }

    /**
     * @return StackManager
     */
    public function getStackManager(): StackManager
    {
        return $this->stackManager;
    }

    /**
     * @param AuthDataManager $authDataManager
     * @return $this
     */
    public function setAuthDataManager(AuthDataManager $authDataManager): self
    {
        $this->authDataManager = $authDataManager;
        return $this;
    }

    /**
     * @return AuthDataManager|null
     */
    public function getAuthDataManager(): ?AuthDataManager
    {
        return $this->authDataManager;
    }

    /**
     * @param ProxyManager $proxyManager
     * @return $this
     */
    public function setProxyManager(ProxyManager $proxyManager): self
    {
        $this->proxyManager = $proxyManager;
        return $this;
    }

    /**
     * @return ProxyManager|null
     */
    public function getProxyManager(): ?ProxyManager
    {
        return $this->proxyManager;
    }

    /**
     * @return DictionaryDataManager
     */
    public function getDictionaryDataManager(): DictionaryDataManager
    {
        if (!$this->dictionaryDataManager) {
            $this->dictionaryDataManager = new DictionaryDataManager($this);
        }

        return $this->dictionaryDataManager;
    }

    /**
     * @return string
     */
    private function getOriginID(): string
    {
        return self::ORIGIN_DATA_ID;
    }
}