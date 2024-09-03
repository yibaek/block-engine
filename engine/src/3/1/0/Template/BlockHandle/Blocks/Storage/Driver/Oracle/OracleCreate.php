<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\Oracle;

use Exception;
use libraries\util\PlanUtil;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\StorageException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\Storage\Driver\Oracle\OracleMgr;
use Ntuple\Synctree\Util\Storage\Driver\Oracle\OracleStorageException;
use Throwable;

class OracleCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-oracle-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $connector;

    /**
     * OracleCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $connector
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $connector = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->connector = $connector;
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
        $this->connector = $this->setBlock($this->storage, $data['template']['connector']);

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
                'connector' => $this->connector->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return OracleMgr
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): OracleMgr
    {
        try {
            return $this->getConnector($blockStorage);
        } catch (OracleStorageException $ex) {
            throw (new StorageException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData())->setData($ex->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Storage-Oracle-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return OracleMgr
     * @throws ISynctreeException
     */
    private function getConnector(array &$blockStorage): OracleMgr
    {
        $connector = $this->connector->do($blockStorage);
        if (empty($connector) || !is_array($connector)) {
            throw (new InvalidArgumentException('Storage-Oracle-Create: Invalid connection'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        // check storage type
        if ('oracle' !== $connector['driver']) {
            throw (new InvalidArgumentException('Storage-Oracle-Create: Invalid connection: Not a oracle connection'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        $isTestMode = PlanUtil::isTestMode($this->storage->getOrigin()->getHeaders());

        return new OracleMgr($this->storage->getLogger(), $connector, $isTestMode);
    }
}