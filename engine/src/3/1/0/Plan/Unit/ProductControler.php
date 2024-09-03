<?php
namespace Ntuple\Synctree\Plan\Unit;

class ProductControler
{
    public const PRODUCT_CONTROL_UNLIMIT_CODE = -1;

    private $productInfo;

    /**
     * ProductControler constructor.
     * @param array $productInfo
     */
    public function __construct(array $productInfo = [])
    {
        $this->productInfo = $productInfo;
    }

    /**
     * @return int
     */
    public function getLimitNosqlKeyCount(): int
    {
        return $this->productInfo['limit_nosql_key'] ?? self::PRODUCT_CONTROL_UNLIMIT_CODE;
    }

    /**
     * @return int
     */
    public function getLimitNosqlExpiryPeriod(): int
    {
        return $this->productInfo['limit_nosql_expiry'] ?? self::PRODUCT_CONTROL_UNLIMIT_CODE;
    }
}