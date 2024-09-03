<?php
namespace Ntuple\Synctree\Protocol\Http;

use Exception;
use Generator;
use Ntuple\Synctree\Log\LogMessage;
use Psr\Log\LogLevel;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;

class HttpLogMiddleware
{
    private const DEFAULT_MESSAGE_FORMAT_DEBUG = '[request::{request}][response::{response}]';
    private const DEFAULT_MESSAGE_FORMAT_PRODUCTION = '{hostname} {req_header_User-Agent} - [{date_common_log}] \"{method} {target} HTTP/{version}\" {code} {res_header_Content-Length}';

    private $logger;
    private $messageFormats;

    /**
     * HttpLogMiddleware constructor.
     * @param LogMessage $logger
     * @param array|null $messageFormats
     */
    public function __construct(LogMessage $logger, array $messageFormats = null)
    {
        $this->logger = $logger;
        $this->messageFormats = $messageFormats;
    }

    /**
     * @return Generator|null
     * @throws Exception
     */
    public function getLoggingMiddleware(): ?Generator
    {
        $messageFormats = $this->getMessageFormats();

        foreach ($messageFormats as $messageFormat) {
            yield $this->createLoggingMiddleware($messageFormat);
        }
    }

    /**
     * @param string $messageFormat
     * @return callable
     * @throws Exception
     */
    private function createLoggingMiddleware(string $messageFormat): callable
    {
        return Middleware::log($this->logger->getLogger()->getLogger(), new MessageFormatter($messageFormat), LogLevel::INFO);
    }

    /**
     * @return array
     */
    private function getMessageFormats(): array
    {
        if (!empty($this->messageFormats)) {
            return $this->messageFormats;
        }

        if (APP_ENV === APP_ENV_PRODUCTION) {
            return [
                self::DEFAULT_MESSAGE_FORMAT_PRODUCTION
            ];
        }

        return [
            self::DEFAULT_MESSAGE_FORMAT_DEBUG
        ];
    }
}
