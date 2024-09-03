<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Redis;

use Exception;
use JsonException;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class RedisConnector implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-redis-connect-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connectID;

    /**
     * RedisConnector constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connectID
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $connectID = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->connectID = $connectID;
    }

    /**
     * @param array $data
     * @return IBlock
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->connectID = $this->setBlock($this->storage, $data['template']['connect-id']);

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'extra' => $this->extra->getData(),
            'template' => [
                'connect-id' => $this->connectID->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     * @throws Throwable
     */
    public function do(array &$blockStorage): array
    {
        try {
            return $this->getConnectInfo($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Redis-Connector'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws Throwable
     */
    private function getConnectInfo(array &$blockStorage): array
    {
        $storageData = $this->storage->getRdbStudioResource()->getHandler()->executeGetStorageDetail($this->connectID->do($blockStorage), CommonUtil::getStorageEncryptKey());

        // get connect info
        $connectInfo = $this->getStorageConnectInfo($storageData['storage_db_info']);

        return array_merge(['driver' => $storageData['storage_type']], $connectInfo);
    }

    /**
     * @param $connectInfo
     * @return array
     * @throws JsonException
     */
    private function getStorageConnectInfo(string $connectInfo): array
    {
        try {
            $info = json_decode($connectInfo, JSON_UNESCAPED_UNICODE, 512, JSON_THROW_ON_ERROR);

            return array_merge(['operation_mode' => $info['storage_operation_mode']], $this->getSingleModeConnectInfo($info));
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * @param array $info
     * @return array
     */
    private function getSingleModeConnectInfo(array $info): array
    {
        return [
            'host' => $info['storage_host'],
            'port' => $info['storage_port'],
            'auth' => $info['storage_password'] ?? null
        ];
    }
}