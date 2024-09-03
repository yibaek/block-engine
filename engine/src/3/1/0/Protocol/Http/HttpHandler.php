<?php
namespace Ntuple\Synctree\Protocol\Http;

use Exception;
use GuzzleHttp\HandlerStack;
use Ntuple\Synctree\Log\LogMessage;

class HttpHandler
{
    private $logger;
    private $handlerStack;

    /**
     * HttpHandler constructor.
     * @param LogMessage $logger
     */
    public function __construct(LogMessage $logger) {
        // create handler stack
        $this->handlerStack = HandlerStack::create();
        $this->logger = $logger;
    }

    /**
     * @return HandlerStack
     */
    public function getHandlerStack(): HandlerStack
    {
        return $this->handlerStack;
    }

    /**
     * @param array|null $messageFormats
     * @return HttpHandler
     * @throws Exception
     */
    public function enableLogging(array $messageFormats = null): HttpHandler
    {
        // get logging middleware
        $logMiddleware = new HttpLogMiddleware($this->logger, $messageFormats);
        $messageFormatMiddlewares = $logMiddleware->getLoggingMiddleware();

        // push middleware
        foreach ($messageFormatMiddlewares as $middleware) {
            $this->handlerStack->unshift($middleware);
        }

        return $this;
    }
}
