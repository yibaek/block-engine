<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Convert\CDecimalHexDecode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Convert\CDecimalHexEncode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Convert\CHexDecode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Convert\CHexEncode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Crypto\CDecrypt;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Crypto\CEncrypt;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Crypto\RSA\CRSADecrypt;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Crypto\RSA\CRSAEncrypt;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DArray\CArrayContainsKey;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DArray\CArrayCount;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DArray\CArrayEqual;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime\CDateTimeAdd;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime\CDateTimeCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime\CDateTimeDiff;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime\CDateTimeDiffFormat;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime\CDateTimeFormat;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime\CDateTimeInterval;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime\CDateTimeOffset;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime\CDateTimeSubtract;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\DateTime\CDateTimeTimestamp;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalAbs;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalAdd;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalCeil;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalDivide;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalFloor;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalMultiply;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalRound;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalSubtract;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalToFloat;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalToInteger;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Decimal\CDecimalToString;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\EnDecode\CBase64Decode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\EnDecode\CBase64Encode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\EnDecode\CUrlDecode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\EnDecode\CUrlEncode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Handle\CIsArray;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Handle\CIsBoolean;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Handle\CIsFloat;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Handle\CIsInteger;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Handle\CIsNull;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Handle\CIsNumeric;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Handle\CIsString;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Handle\CToFloat;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Handle\CToInteger;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Handle\CToString;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Hash\CHashGenerate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Hash\CHashHmacGenerate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\ObjectNotation\CJsonDecode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\ObjectNotation\CJsonEncode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\ObjectNotation\CXmlDecode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\ObjectNotation\CXmlEncode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Random\RandomInteger;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringCharsetEncode;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringConcat;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringFormat;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringIndex;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringLength;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringLtrim;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringRegexReplace;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringRegexSplit;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringReplace;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringRtrim;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringSplit;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringSubString;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\String\CStringToArray;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class CommonUtilManager implements IBlock
{
    public const TYPE = 'common-util';

    private $storage;
    private $block;

    /**
     * CommonUtilManager constructor.
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
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        switch ($data['action']) {
            case CArrayContainsKey::ACTION:
                $this->block = (new CArrayContainsKey($this->storage))->setData($data);
                return $this;

            case CArrayCount::ACTION:
                $this->block = (new CArrayCount($this->storage))->setData($data);
                return $this;

            case CArrayEqual::ACTION:
                $this->block = (new CArrayEqual($this->storage))->setData($data);
                return $this;

            case CStringConcat::ACTION:
                $this->block = (new CStringConcat($this->storage))->setData($data);
                return $this;

            case CStringIndex::ACTION:
                $this->block = (new CStringIndex($this->storage))->setData($data);
                return $this;

            case CStringLength::ACTION:
                $this->block = (new CStringLength($this->storage))->setData($data);
                return $this;

            case CStringLtrim::ACTION:
                $this->block = (new CStringLtrim($this->storage))->setData($data);
                return $this;

            case CStringRtrim::ACTION:
                $this->block = (new CStringRtrim($this->storage))->setData($data);
                return $this;

            case CStringToArray::ACTION:
                $this->block = (new CStringToArray($this->storage))->setData($data);
                return $this;

            case CStringRegexReplace::ACTION:
                $this->block = (new CStringRegexReplace($this->storage))->setData($data);
                return $this;

            case CStringRegexSplit::ACTION:
                $this->block = (new CStringRegexSplit($this->storage))->setData($data);
                return $this;

            case CStringReplace::ACTION:
                $this->block = (new CStringReplace($this->storage))->setData($data);
                return $this;

            case CStringSplit::ACTION:
                $this->block = (new CStringSplit($this->storage))->setData($data);
                return $this;

            case CStringSubString::ACTION:
                $this->block = (new CStringSubString($this->storage))->setData($data);
                return $this;

            case CStringFormat::ACTION:
                $this->block = (new CStringFormat($this->storage))->setData($data);
                return $this;

            case CStringCharsetEncode::ACTION:
                $this->block = (new CStringCharsetEncode($this->storage))->setData($data);
                return $this;

            case CEncrypt::ACTION:
                $this->block = (new CEncrypt($this->storage))->setData($data);
                return $this;

            case CDecrypt::ACTION:
                $this->block = (new CDecrypt($this->storage))->setData($data);
                return $this;

            case CRSAEncrypt::ACTION:
                $this->block = (new CRSAEncrypt($this->storage))->setData($data);
                return $this;

            case CRSADecrypt::ACTION:
                $this->block = (new CRSADecrypt($this->storage))->setData($data);
                return $this;

            case CBase64Encode::ACTION:
                $this->block = (new CBase64Encode($this->storage))->setData($data);
                return $this;

            case CBase64Decode::ACTION:
                $this->block = (new CBase64Decode($this->storage))->setData($data);
                return $this;

            case CUrlEncode::ACTION:
                $this->block = (new CUrlEncode($this->storage))->setData($data);
                return $this;

            case CUrlDecode::ACTION:
                $this->block = (new CUrlDecode($this->storage))->setData($data);
                return $this;

            case CHexEncode::ACTION:
                $this->block = (new CHexEncode($this->storage))->setData($data);
                return $this;

            case CHexDecode::ACTION:
                $this->block = (new CHexDecode($this->storage))->setData($data);
                return $this;

            case CDecimalHexEncode::ACTION:
                $this->block = (new CDecimalHexEncode($this->storage))->setData($data);
                return $this;

            case CDecimalHexDecode::ACTION:
                $this->block = (new CDecimalHexDecode($this->storage))->setData($data);
                return $this;

            case CHashGenerate::ACTION:
                $this->block = (new CHashGenerate($this->storage))->setData($data);
                return $this;

            case CHashHmacGenerate::ACTION:
                $this->block = (new CHashHmacGenerate($this->storage))->setData($data);
                return $this;

            case CJsonEncode::ACTION:
                $this->block = (new CJsonEncode($this->storage))->setData($data);
                return $this;

            case CJsonDecode::ACTION:
                $this->block = (new CJsonDecode($this->storage))->setData($data);
                return $this;

            case CXmlEncode::ACTION:
                $this->block = (new CXmlEncode($this->storage))->setData($data);
                return $this;

            case CXmlDecode::ACTION:
                $this->block = (new CXmlDecode($this->storage))->setData($data);
                return $this;

            case CIsArray::ACTION:
                $this->block = (new CIsArray($this->storage))->setData($data);
                return $this;

            case CIsBoolean::ACTION:
                $this->block = (new CIsBoolean($this->storage))->setData($data);
                return $this;

            case CIsFloat::ACTION:
                $this->block = (new CIsFloat($this->storage))->setData($data);
                return $this;

            case CIsInteger::ACTION:
                $this->block = (new CIsInteger($this->storage))->setData($data);
                return $this;

            case CIsNull::ACTION:
                $this->block = (new CIsNull($this->storage))->setData($data);
                return $this;

            case CIsString::ACTION:
                $this->block = (new CIsString($this->storage))->setData($data);
                return $this;

            case CIsNumeric::ACTION:
                $this->block = (new CIsNumeric($this->storage))->setData($data);
                return $this;

            case CDateTimeAdd::ACTION:
                $this->block = (new CDateTimeAdd($this->storage))->setData($data);
                return $this;

            case CDateTimeCreate::ACTION:
                $this->block = (new CDateTimeCreate($this->storage))->setData($data);
                return $this;

            case CDateTimeDiff::ACTION:
                $this->block = (new CDateTimeDiff($this->storage))->setData($data);
                return $this;

            case CDateTimeDiffFormat::ACTION:
                $this->block = (new CDateTimeDiffFormat($this->storage))->setData($data);
                return $this;

            case CDateTimeFormat::ACTION:
                $this->block = (new CDateTimeFormat($this->storage))->setData($data);
                return $this;

            case CDateTimeInterval::ACTION:
                $this->block = (new CDateTimeInterval($this->storage))->setData($data);
                return $this;

            case CDateTimeOffset::ACTION:
                $this->block = (new CDateTimeOffset($this->storage))->setData($data);
                return $this;

            case CDateTimeSubtract::ACTION:
                $this->block = (new CDateTimeSubtract($this->storage))->setData($data);
                return $this;

            case CDateTimeTimestamp::ACTION:
                $this->block = (new CDateTimeTimestamp($this->storage))->setData($data);
                return $this;

            case CDecimalCreate::ACTION:
                $this->block = (new CDecimalCreate($this->storage))->setData($data);
                return $this;

            case CDecimalAdd::ACTION:
                $this->block = (new CDecimalAdd($this->storage))->setData($data);
                return $this;

            case CDecimalSubtract::ACTION:
                $this->block = (new CDecimalSubtract($this->storage))->setData($data);
                return $this;

            case CDecimalMultiply::ACTION:
                $this->block = (new CDecimalMultiply($this->storage))->setData($data);
                return $this;

            case CDecimalDivide::ACTION:
                $this->block = (new CDecimalDivide($this->storage))->setData($data);
                return $this;

            case CDecimalToString::ACTION:
                $this->block = (new CDecimalToString($this->storage))->setData($data);
                return $this;

            case CDecimalToInteger::ACTION:
                $this->block = (new CDecimalToInteger($this->storage))->setData($data);
                return $this;

            case CDecimalToFloat::ACTION:
                $this->block = (new CDecimalToFloat($this->storage))->setData($data);
                return $this;

            case CDecimalAbs::ACTION:
                $this->block = (new CDecimalAbs($this->storage))->setData($data);
                return $this;

            case CDecimalRound::ACTION:
                $this->block = (new CDecimalRound($this->storage))->setData($data);
                return $this;

            case CDecimalFloor::ACTION:
                $this->block = (new CDecimalFloor($this->storage))->setData($data);
                return $this;

            case CDecimalCeil::ACTION:
                $this->block = (new CDecimalCeil($this->storage))->setData($data);
                return $this;

            case CToFloat::ACTION:
                $this->block = (new CToFloat($this->storage))->setData($data);
                return $this;

            case CToInteger::ACTION:
                $this->block = (new CToInteger($this->storage))->setData($data);
                return $this;

            case CToString::ACTION:
                $this->block = (new CToString($this->storage))->setData($data);
                return $this;

            case RandomInteger::ACTION:
                $this->block = (new RandomInteger($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid common-util action[action:'.$data['action'].']');
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
     * @return mixed
     */
    public function do(array &$blockStorage)
    {
        return $this->block->do($blockStorage);
    }
}