<?php
namespace Ntuple\Synctree\Template\BlockHandle;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\AccessControl\AccessControlManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Analytics\AnalyticsManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\AuthorizationManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\BizunitManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Operator\Context\ResponseContextManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Operator\OperatorManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Debug\DebugManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\File\FileManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Helper\Document\DocumentManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Helper\Helper\HelperManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Log\LogManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Loop\DFor\CForManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Property\PropertyManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\EndPoint\EndPointManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Option\OptionManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\ProtocolManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Unit\ProtocolUnitManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Unit\Soap\SoapRepresentsManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\HashMap\CHashMapManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\ArrayList\CArrayListManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\Pair\CPairManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Collection\Parameter\CParameterManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\CommonUtilManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Custom\Util\CustomUtilManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Exception\Handler\ExceptionHandlerManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Logic\Choice\CChoiceManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Logic\CLogicManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Logic\Expression\CExpressionManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Logic\Expression\Condition\CConditionManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Loop\DForeach\CForeachManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Loop\Control\CLoopControlManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Math\CMathManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\CMiscellaneousManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive\CPrimitiveManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Share\Data\DataShareManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\StorageManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Stream\StreamManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\System\SystemManager;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Variable\VariableManager;
use Ntuple\Synctree\Util\Extra\ExtraManager;

trait BlockHandleTrait
{
    /**
     * @param PlanStorage $storage
     * @param array $data
     * @return IBlock
     * @throws Exception
     */
    public function setBlock(PlanStorage $storage, array $data): IBlock
    {
        switch ($data['type']) {
            case VariableManager::TYPE:
                return (new VariableManager($storage))->setData($data);

            case CPrimitiveManager::TYPE:
                return (new CPrimitiveManager($storage))->setData($data);

            case CArrayListManager::TYPE:
                return (new CArrayListManager($storage))->setData($data);

            case CHashMapManager::TYPE:
                return (new CHashMapManager($storage))->setData($data);

            case CPairManager::TYPE:
                return (new CPairManager($storage))->setData($data);

            case CLogicManager::TYPE:
                return (new CLogicManager($storage))->setData($data);

            case CChoiceManager::TYPE:
                return (new CChoiceManager($storage))->setData($data);

            case CExpressionManager::TYPE:
                return (new CExpressionManager($storage))->setData($data);

            case CConditionManager::TYPE:
                return (new CConditionManager($storage))->setData($data);

            case CMathManager::TYPE:
                return (new CMathManager($storage))->setData($data);

            case CForeachManager::TYPE:
                return (new CForeachManager($storage))->setData($data);

            case CommonUtilManager::TYPE:
                return (new CommonUtilManager($storage))->setData($data);

            case CMiscellaneousManager::TYPE:
                return (new CMiscellaneousManager($storage))->setData($data);

            case DataShareManager::TYPE:
                return (new DataShareManager($storage))->setData($data);

            case BizunitManager::TYPE:
                return (new BizunitManager($storage))->setData($data);

            case OperatorManager::TYPE:
                return (new OperatorManager($storage))->setData($data);

            case ResponseContextManager::TYPE:
                return (new ResponseContextManager($storage))->setData($data);

            case ProtocolManager::TYPE:
                return (new ProtocolManager($storage))->setData($data);

            case SoapRepresentsManager::TYPE:
                return (new SoapRepresentsManager($storage))->setData($data);

            case ProtocolUnitManager::TYPE:
                return (new ProtocolUnitManager($storage))->setData($data);

            case OptionManager::TYPE:
                return (new OptionManager($storage))->setData($data);

            case EndPointManager::TYPE:
                return (new EndPointManager($storage))->setData($data);

            case CParameterManager::TYPE:
                return (new CParameterManager($storage))->setData($data);

            case CustomUtilManager::TYPE:
                return (new CustomUtilManager($storage))->setData($data);

            case PropertyManager::TYPE:
                return (new PropertyManager($storage))->setData($data);

            case ExceptionHandlerManager::TYPE:
                return (new ExceptionHandlerManager($storage))->setData($data);

            case CLoopControlManager::TYPE:
                return (new CLoopControlManager($storage))->setData($data);

            case LogManager::TYPE:
                return (new LogManager($storage))->setData($data);

            case AuthorizationManager::TYPE:
                return (new AuthorizationManager($storage))->setData($data);

            case StreamManager::TYPE:
                return (new StreamManager($storage))->setData($data);

            case AccessControlManager::TYPE:
                return (new AccessControlManager($storage))->setData($data);

            case DocumentManager::TYPE:
                return (new DocumentManager($storage))->setData($data);

            case HelperManager::TYPE:
                return (new HelperManager($storage))->setData($data);

            case DebugManager::TYPE:
                return (new DebugManager($storage))->setData($data);

            case StorageManager::TYPE:
                return (new StorageManager($storage))->setData($data);

            case AnalyticsManager::TYPE:
                return (new AnalyticsManager($storage))->setData($data);

            case FileManager::TYPE:
                return (new FileManager($storage))->setData($data);

            case SystemManager::TYPE:
                return (new SystemManager($storage))->setData($data);

            case CForManager::TYPE:
                return (new CForManager($storage))->setData($data);

            default:
                throw new \RuntimeException('invalid block type[type:'.$data['type'].']');
        }
    }

    /**
     * @param PlanStorage $storage
     * @param array $datas
     * @return BlockAggregator
     * @throws Exception
     */
    public function setBlocks(PlanStorage $storage, array $datas): BlockAggregator
    {
        $aggregator = new BlockAggregator();
        foreach ($datas as $data) {
            $aggregator->addBlock($this->setBlock($storage, $data));
        }

        return $aggregator;
    }

    /**
     * @param PlanStorage $storage
     * @param array $data
     * @return ExtraManager
     */
    public function setExtra(PlanStorage $storage, array $data): ExtraManager
    {
        return (new ExtraManager($storage))->setData($data);
    }

    /**
     * 사용자가 식별하기 위한 블럭 레이블을 생성한다.
     * ex) "storage-driver-nosql" -> "Storage-Driver-Nosql"
     *
     * @return string
     * @since SRT-231
     */
    protected function getLabel(): string
    {
        return ucwords(self::TYPE . '-' . self::ACTION, '-');
    }
}