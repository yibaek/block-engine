<?php
namespace libraries\product;

use Throwable;
use Ntuple\Synctree\Plan\PlanStorage;

use libraries\exception\ProductControlException;
use Ntuple\Synctree\Util\AccessControl\RateLimit\Rate;
use Ntuple\Synctree\Util\AccessControl\RateLimit\RateLimit;
use Ntuple\Synctree\Util\AccessControl\Exception\LimitExceededException;

class ProductControler
{
    private const PRODUCT_CONTROL_EXCEPTION_CODE = -1;

    private $storage;
    private $productInfo;

    /**
     * ProductControler constructor.
     * @param PlanStorage $planStorage
     * @param array $productInfo
     */
    public function __construct(PlanStorage $planStorage, array $productInfo = [])
    {
        $this->storage = $planStorage;
        $this->productInfo = $productInfo;
    }

    /**
     * @throws Throwable
     */
    public function control(): void
    {
        $this->controlTps();
        $this->controlCall();
    }

    /**
     * @throws Throwable
     */
    private function controlTps(): void
    {
        try {
            if ($this->isControl($this->getLimitTps())) {
                (new RateLimit($this->storage->getRedisResource(), 'product-control-tps-'))
                    ->control($this->getMasterID(), (new Rate())->perSecond($this->getLimitTps()));
            }
        } catch (LimitExceededException $ex) {
            throw (new ProductControlException('TPS exceeded'))->setError(429, 'TPS exceeded', 'TPS exceeded');
        }
    }

    /**
     * @throws Throwable
     */
    private function controlCall(): void
    {
        try {
            if ($this->isControl($this->getLimitCall())) {
                (new RateLimit($this->storage->getRedisResource(), 'product-control'))
                    ->control('daily-call-'.$this->getMasterID(), (new Rate())->perCustom($this->getLimitCall(), $this->getDailyInterval()));
            }
        } catch (LimitExceededException $ex) {
            throw (new ProductControlException('Quota exceeded'))->setError(429, 'Quota exceeded', 'Quota exceeded');
        }
    }

    /**
     * @return int
     */
    private function getMasterID(): int
    {
        return (int) $this->productInfo['master_id'];
    }

    /**
     * @return int
     */
    private function getLimitCall(): int
    {
        return (int) $this->productInfo['limit_call'];
    }

    /**
     * @return int
     */
    private function getLimitTps(): int
    {
        return (int) $this->productInfo['limit_tps'];
    }

    /**
     * @return int
     */
    private function getDailyInterval(): int
    {
        $current = time();

        [$year, $month, $day] = explode('-', date('Y-m-d', $current));
        $endOfDay = mktime(23,59,59, $month, $day, $year);

        return $endOfDay - $current;
    }

    /**
     * @param int $limitCount
     * @return bool
     */
    private function isControl(int $limitCount): bool
    {
        return $limitCount !== self::PRODUCT_CONTROL_EXCEPTION_CODE && $limitCount > 0;
    }
}