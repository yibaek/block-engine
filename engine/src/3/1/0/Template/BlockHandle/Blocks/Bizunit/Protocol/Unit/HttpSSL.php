<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Unit;

use Exception;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Exceptions\TransferException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Protocol\Http\HttpExecutor;
use Ntuple\Synctree\Protocol\Http\HttpHandler;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Context\ProtocolContext;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class HttpSSL implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'protocol-unit';
    public const ACTION = 'http-ssl';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $method;
    private $endPoint;
    private $header;
    private $body;
    private $options;

    /**
     * HttpSSL constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $method
     * @param IBlock|null $endPoint
     * @param IBlock|null $header
     * @param IBlock|null $body
     * @param BlockAggregator|null $options
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $method = null, IBlock $endPoint = null, IBlock $header = null, IBlock $body = null, BlockAggregator $options = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->method = $method;
        $this->endPoint = $endPoint;
        $this->header = $header;
        $this->body = $body;
        $this->options = $options;
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
        $this->method = $this->setBlock($this->storage, $data['template']['method']);
        $this->endPoint = $this->setBlock($this->storage, $data['template']['end-point']);
        $this->header = $this->setBlock($this->storage, $data['template']['header']);
        $this->body = $this->setBlock($this->storage, $data['template']['body']);
        $this->options = $this->setBlocks($this->storage, $data['template']['options']);

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
                'method' => $this->method->getTemplate(),
                'end-point' => $this->endPoint->getTemplate(),
                'header' => $this->header->getTemplate(),
                'body' => $this->body->getTemplate(),
                'options' => $this->getTemplateEachOption()
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
            $reqHeader = $this->getHeader($blockStorage);
            $reqBody = $this->getBody($blockStorage);

            // generate http executor
            $executor = (new HttpExecutor($this->storage->getLogger(), (new HttpHandler($this->storage->getLogger()))->enableLogging()->getHandlerStack()))
                ->setRepository($this->storage->getRedisResource())
                ->setEndPoint($this->getEndpoint($blockStorage))
                ->setMethod($this->getMethod($blockStorage))
                ->setOptions($this->getOptions($blockStorage))
                ->setHeaders($reqHeader)
                ->setBodys($reqBody)
                ->isConvertJson(true);

            // execute
            [$resStatusCode, $resHeader, $resBody] = $executor->execute();

            return [
                new ProtocolContext($reqHeader, $reqBody),
                new ProtocolContext($resHeader, $resBody, $resStatusCode)
            ];
        } catch (ConnectException $ex) {
            $context = $ex->getHandlerContext();
            throw (new TransferException('HttpSSL: '.$context['error'] ?? $ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (BadResponseException $ex) {
            $context = $ex->getHandlerContext();
            throw (new TransferException('HttpSSL: '.$context['error'] ?? $ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (RequestException $ex) {
            $context = $ex->getHandlerContext();
            throw (new TransferException('HttpSSL: '.$context['error'] ?? $ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (GuzzleException $ex) {
            throw (new TransferException($ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('HttpSSL'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
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
     * @return string
     * @throws ISynctreeException
     */
    private function getMethod(array &$blockStorage): string
    {
        $method = $this->method->do($blockStorage);
        if (!is_string($method)) {
            throw (new InvalidArgumentException('HttpSSL: Invalid method'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $method;
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getEndpoint(array &$blockStorage): string
    {
        $endpoint = $this->endPoint->do($blockStorage);
        if (!is_string($endpoint)) {
            throw (new InvalidArgumentException('HttpSSL: Invalid endpoint'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $endpoint;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getHeader(array &$blockStorage): array
    {
        $header = $this->header->do($blockStorage);
        if (!is_array($header)) {
            throw (new InvalidArgumentException('HttpSSL: Invalid header'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $header;
    }

    /**
     * @param array $blockStorage
     * @return mixed
     */
    private function getBody(array &$blockStorage)
    {
        return $this->body->do($blockStorage);
    }
}