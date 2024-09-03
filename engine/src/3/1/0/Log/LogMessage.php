<?php
namespace Ntuple\Synctree\Log;

use DateTime;
use Exception;
use Throwable;
use Monolog\Logger;
use Ntuple\Synctree\Log\Processor\ExceptionProcessor;
use Ntuple\Synctree\Log\Processor\QueryProcessor;

class LogMessage
{
    public const CONSOLE_LOG_TYPE_SYSTEM = 0;
    public const CONSOLE_LOG_TYPE_USER = 1;

    private $logger;
    private $consoleType;
    private $messagePool;

    /**
     * LogMessage constructor.
     * @param CreateLogger|null $logger
     */
    public function __construct(CreateLogger $logger = null)
    {
        $this->logger = $logger ?? new CreateLogger();
        $this->consoleType = self::CONSOLE_LOG_TYPE_SYSTEM;
        $this->messagePool = [];
    }

    /**
     * @param CreateLogger $logger
     * @return static
     */
    public static function create(CreateLogger $logger)
    {
        return new static($logger);
    }

    /**
     * @return CreateLogger
     */
    public function getLogger(): CreateLogger
    {
        return $this->logger;
    }

    /**
     * @param string $logs
     * @param string $class
     * @param string $func
     * @param string $line
     * @throws Exception
     */
    public function info(string $logs, string $class = '', string $func = '', string $line = ''): void
    {
        // generates a backtrace
        [$class, $func, $line] = $this->getTraceInfo($class, $func, $line);

        // logging
        $this->logger->getLogger()->info($this->makeMessage($logs, $class, $func, $line));
    }

    /**
     * @param string $logs
     * @param string $class
     * @param string $func
     * @param string $line
     * @throws Exception
     */
    public function error(string $logs, string $class = '', string $func = '', string $line = ''): void
    {
        // generates a backtrace
        [$class, $func, $line] = $this->getTraceInfo($class, $func, $line);

        // logging
        $this->logger->getLogger()->error($this->makeMessage($logs, $class, $func, $line));
    }

    /**
     * @param string $logs
     * @param string $class
     * @param string $func
     * @param string $line
     * @throws Exception
     */
    public function debug(string $logs, string $class = '', string $func = '', string $line = ''): void
    {
        // generates a backtrace
        [$class, $func, $line] = $this->getTraceInfo($class, $func, $line);

        // logging
        $this->logger->getLogger()->debug($this->makeMessage($logs, $class, $func, $line));
    }


    /**
     * 쿼리와 관련된 logging (추가 정보 로깅하기 위해 별도 method 분리)
     * @param string $queryType : SELECT, INSERT, UPDATE, DELETE
     * @param string $query
     * @param array|null $bindings
     * @throws Exception
     * @since SYN-397
     */
    public function query(string $queryType, string $query, ?array $bindings): void
    {
        // generates a backtrace
        $debugInfo = debug_backtrace();
        [$class, $func, $line] = [
            $debugInfo[1]['class'],
            $debugInfo[1]['function'],
            $debugInfo[0]['line']
        ];

        // 실행 정보
        $executeInfo = [
            'class' => $class,
            'function' => $func,
            'line' => $line
        ];

        if (isset($debugInfo[2]['class'])) { // 이전 실행 단계 있는 경우
            $executeInfo = [
                'class' => $debugInfo[2]['class'],
                'function' => $debugInfo[2]['function'] ?? 'undefined',
                'line' => $debugInfo[2]['line'] ?? 'undefined'
            ];
        }
        unset($debugInfo);

        // generates context
        $context = ['log_type' => 'query'];

        // logging
        $this->logger->addProcessor(new QueryProcessor($queryType, $query, $bindings, $executeInfo))
            ->getLogger()
            ->debug($this->makeMessage($query, $class, $func, $line), $context);
        $this->logger->popProcessor(); // 마지막 processor 제거
    }

    /**
     * @param Throwable $ex
     * @param null $reference
     * @throws Exception
     */
    public function exception(Throwable $ex, $reference = null): void
    {
        // generates a backtrace
        [$class, $func, $line] = $this->getTraceInfo('', '', '');

        // generates context
        $context = ['log_type' => 'exception'];

        if (!empty($reference)) {
            $context['reference'] = $reference;
        }

        // logging
        $this->logger->addProcessor(new ExceptionProcessor($ex))
            ->getLogger()
            ->error($this->makeMessage($ex->getMessage(), $class, $func, $line), $context);
        $this->logger->popProcessor(); // 마지막 processor 제거
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setConsoleLogType(string $type): LogMessage
    {
        $this->consoleType = $type;
        return $this;
    }

    /**
     * @param string $logs
     * @throws Exception
     */
    public function infoOnConsoleLog(string $logs): void
    {
        $level = Logger::INFO;
        try {
            // add message to pool
            $this->addMessagePool($level, $logs);
        } catch (Throwable $ex) {
            $this->exception($ex);
        }
    }

    /**
     * @param string $logs
     * @throws Exception
     */
    public function errorOnConsoleLog(string $logs): void
    {
        $level = Logger::ERROR;
        try {
            // add message to pool
            $this->addMessagePool($level, $logs);
        } catch (Throwable $ex) {
            $this->exception($ex);
        }
    }

    /**
     * @param string $logs
     * @throws Exception
     */
    public function debugOnConsoleLog(string $logs): void
    {
        $level = Logger::DEBUG;
        try {
            // add message to pool
            $this->addMessagePool($level, $logs);
        } catch (Throwable $ex) {
            $this->exception($ex);
        }
    }

    /**
     * @return array
     */
    public function getMessagePool(): array
    {
        return $this->messagePool;
    }

    /**
     * @param string $logs
     * @param string $class
     * @param string $func
     * @param string $line
     * @return string
     */
    private function makeMessage(string $logs, string $class, string $func, string $line): string
    {
        // make message
        $message = '[' . sprintf('%s', $class . '\\' . $func . '(line:' . $line . ')') . ']';
        $message .= $logs . PHP_EOL;

        return $message;
    }

    /**
     * @param string $class
     * @param string $func
     * @param string $line
     * @return array
     */
    private function getTraceInfo(string $class, string $func, string $line): array
    {
        // generates a backtrace
        $debugInfo = debug_backtrace();

        return [
            empty($class) ?$debugInfo[2]['class'] :$class,
            empty($func) ?$debugInfo[2]['function'] :$func,
            empty($line) ?$debugInfo[1]['line'] :$line];
    }

    /**
     * @param int $level
     * @param string $message
     */
    private function addMessagePool(int $level, string $message): void
    {
        $this->messagePool[] = ['type' => $this->consoleType, 'level' => $level, 'message' => $message, 'date' => (new DateTime('now'))->format('U.u')];
    }
}
