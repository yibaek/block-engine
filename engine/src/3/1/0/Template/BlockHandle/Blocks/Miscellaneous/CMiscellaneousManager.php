<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CMiscellaneousManager implements IBlock
{
    public const TYPE = 'miscellaneous';

    private $storage;
    private $block;

    /**
     * CMiscellaneousManager constructor.
     * @param PlanStorage $storage
     * @param IBlock|null $block
     */
    public function __construct(PlanStorage $storage, IBlock $block = null)
    {
        $this->storage = $storage;
        $this->block = $block;
    }

    /**
     * @param array $data
     * @return IBlock
     */
    public function setData(array $data): IBlock
    {
        switch ($data['action']) {
            case CArithmeticOperator::ACTION:
                $this->block = (new CArithmeticOperator($this->storage))->setData($data);
                return $this;

            case CCharacterEncodingType::ACTION:
                $this->block = (new CCharacterEncodingType($this->storage))->setData($data);
                return $this;

            case CComparisonOperator::ACTION:
                $this->block = (new CComparisonOperator($this->storage))->setData($data);
                return $this;

            case CCryptoMethod::ACTION:
                $this->block = (new CCryptoMethod($this->storage))->setData($data);
                return $this;

            case CCryptoOption::ACTION:
                $this->block = (new CCryptoOption($this->storage))->setData($data);
                return $this;

            case CDateTimeIntervalType::ACTION:
                $this->block = (new CDateTimeIntervalType($this->storage))->setData($data);
                return $this;

            case CDateTimeTimezone::ACTION:
                $this->block = (new CDateTimeTimezone($this->storage))->setData($data);
                return $this;

            case CExceptionType::ACTION:
                $this->block = (new CExceptionType($this->storage))->setData($data);
                return $this;

            case CHashAlgorithm::ACTION:
                $this->block = (new CHashAlgorithm($this->storage))->setData($data);
                return $this;

            case CHashHmacAlgorithm::ACTION:
                $this->block = (new CHashHmacAlgorithm($this->storage))->setData($data);
                return $this;

            case CHexByteWiseType::ACTION:
                $this->block = (new CHexByteWiseType($this->storage))->setData($data);
                return $this;

            case CJsonEncodeOption::ACTION:
                $this->block = (new CJsonEncodeOption($this->storage))->setData($data);
                return $this;

            case CJsonDecodeOption::ACTION:
                $this->block = (new CJsonDecodeOption($this->storage))->setData($data);
                return $this;

            case CLogicalOperator::ACTION:
                $this->block = (new CLogicalOperator($this->storage))->setData($data);
                return $this;

            case CParameterType::ACTION:
                $this->block = (new CParameterType($this->storage))->setData($data);
                return $this;

            case CProtocolMethod::ACTION:
                $this->block = (new CProtocolMethod($this->storage))->setData($data);
                return $this;

            case CProtocolContentType::ACTION:
                $this->block = (new CProtocolContentType($this->storage))->setData($data);
                return $this;

            case CXmlEncodeEncodingType::ACTION:
                $this->block = (new CXmlEncodeEncodingType($this->storage))->setData($data);
                return $this;

            case CJwsAlgorithm::ACTION:
                $this->block = (new CJwsAlgorithm($this->storage))->setData($data);
                return $this;

            case CProcedureParameterType::ACTION:
                $this->block = (new CProcedureParameterType($this->storage))->setData($data);
                return $this;

            case CProcedureParameterMode::ACTION:
                $this->block = (new CProcedureParameterMode($this->storage))->setData($data);
                return $this;

            case CSamlAttributeNameFormat::ACTION:
                $this->block = (new CSamlAttributeNameFormat($this->storage))->setData($data);
                return $this;

            case CSamlAuthnStatementContext::ACTION:
                $this->block = (new CSamlAuthnStatementContext($this->storage))->setData($data);
                return $this;

            case CSamlSubjectNameIdFormat::ACTION:
                $this->block = (new CSamlSubjectNameIdFormat($this->storage))->setData($data);
                return $this;

            case CRSACryptoOption::ACTION:
                $this->block = (new CRSACryptoOption($this->storage))->setData($data);
                return $this;

            case CFileFlag::ACTION:
                $this->block = (new CFileFlag($this->storage))->setData($data);
                return $this;

            case S3OptionStorageClass::ACTION:
                $this->block = (new S3OptionStorageClass($this->storage))->setData($data);
                return $this;

            case ProtocolContentEncoding::ACTION:
                $this->block = (new ProtocolContentEncoding($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid miscellaneous action[action:'.$data['action'].']');
        }
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return $this->block->getTemplate();
    }

    /**
     * @param array $blockStorage
     */
    public function do(array &$blockStorage)
    {
        return $this->block->do($blockStorage);
    }
}