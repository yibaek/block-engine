<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Unit;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Exceptions\TransferException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Protocol\Soap\SoapExecutor;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Context\ProtocolContext;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use SoapFault;
use Throwable;

class Soap implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'protocol-unit';
    public const ACTION = 'soap';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $wsdl;
    private $options;
    private $headers;
    private $functionName;
    private $argument;

    /**
     * Soap constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $wsdl
     * @param BlockAggregator|null $options
     * @param BlockAggregator|null $headers
     * @param IBlock|null $functionName
     * @param IBlock|null $argument
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $wsdl = null, BlockAggregator $options = null, BlockAggregator $headers = null, IBlock $functionName = null, IBlock $argument = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->wsdl = $wsdl;
        $this->options = $options;
        $this->headers = $headers;
        $this->functionName = $functionName;
        $this->argument = $argument;
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
        $this->wsdl = $this->setBlock($this->storage, $data['template']['wsdl']);
        $this->options = $this->setBlocks($this->storage, $data['template']['options']);
        $this->headers = $this->setBlocks($this->storage, $data['template']['headers']);
        $this->functionName = $this->setBlock($this->storage, $data['template']['function-name']);
        $this->argument = $this->setBlock($this->storage, $data['template']['argument']);

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
                'wsdl' => $this->wsdl->getTemplate(),
                'options' => $this->getTemplateEachOption(),
                'headers' => $this->getTemplateEachHeader(),
                'function-name' => $this->functionName->getTemplate(),
                'argument' => $this->argument->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): array
    {
        try {
            $argument = $this->getArguments($blockStorage);

            // generate http executor
            $executor = (new SoapExecutor($this->storage->getLogger()))
                ->setWsdl($this->getWsdl($blockStorage))
                ->setOptions($this->getOptions($blockStorage))
                ->setFunctioName($this->getFunctioName($blockStorage))
                ->setArguments($argument)
                ->isConvertXml(false)
                ->isEnableLogging(true);

            // set headers
            $headers = [];
            foreach ($this->headers as $header) {
                [$namespace, $name, $data, $mustunderstand] = $header->do($blockStorage);
                $executor->setHeaders($namespace, $name, $data, $mustunderstand);
                $headers[] = $data;
            }

            // execute
            [$resStatusCode, $resHeader, $resBody] = $executor->execute();

            return [
                new ProtocolContext($headers, $argument),
                new ProtocolContext($resHeader, $resBody, $resStatusCode)
            ];
        } catch (SoapFault $ex) {
            throw (new TransferException('Soap: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Soap'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachHeader(): array
    {
        $resData = [];
        foreach ($this->headers as $header) {
            $resData[] = $header->getTemplate();
        }

        return $resData;
    }

    /**
     * @return array
     */
    private function getTemplateEachOption(): array
    {
        $resData = [];
        foreach ($this->options as $option) {
            $resData[] = $option->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return array
     */
    private function getOptions(array &$blockStorage): array
    {
        $resData = [];
        foreach ($this->options as $option) {
            foreach ($option->do($blockStorage) as $key=>$value) {
                $resData[$key] = $value;
            }
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getWsdl(array &$blockStorage): string
    {
        $wsdl = $this->wsdl->do($blockStorage);
        if (!is_string($wsdl)) {
            throw (new InvalidArgumentException('Soap: Invalid wsdl'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $wsdl;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getFunctioName(array &$blockStorage): string
    {
        $functionName = $this->functionName->do($blockStorage);
        if (!is_string($functionName)) {
            throw (new InvalidArgumentException('Soap: Invalid function name'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $functionName;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getArguments(array &$blockStorage): array
    {
        $argument = $this->argument->do($blockStorage);
        if (!is_array($argument)) {
            throw (new InvalidArgumentException('Soap: Invalid argument'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $argument;
    }
}