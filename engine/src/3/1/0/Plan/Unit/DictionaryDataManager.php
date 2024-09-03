<?php declare(strict_types=1);
namespace Ntuple\Synctree\Plan\Unit;

use Ntuple\Synctree\Plan\PlanStorage;

/**
 * @since SYN-569
 */
class DictionaryDataManager
{
    /** @var PlanStorage  */
    private $storage;

    /** @var array  */
    private $datas;

    /**
     * @param PlanStorage $storage
     */
    public function __construct(PlanStorage $storage)
    {
        $this->storage = $storage;
        $this->datas = [];
    }

    /**
     * @param int $id Dictionary detail ID
     * @return array
     */
    public function getDictionaryData(int $id): array
    {
        if (array_key_exists($id, $this->datas)) {
            return $this->datas[$id];
        }

        return $this->fetchDataWithCache($id);
    }

    /**
     * @param int $id
     * @return array
     */
    private function fetchDataWithCache(int $id): array
    {
        $data = $this->storage->getRdbStudioResource()->getHandler()->executeGetDictionaryDetail($id, $this->storage->getTransactionManager()->getEnvironment());

        // cache the dictionary data
        $this->datas[$id] = $data;

        return $data;
    }
}