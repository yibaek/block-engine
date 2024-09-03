<?php
namespace libraries\log;

use Exception;
use Throwable;
use Monolog\Logger;
use libraries\log\processor\QueryProcessor;
use libraries\log\processor\ExceptionProcessor;

class LogMessage
{
    private static $logger;

    /**
     * logger 세팅
     *
     * @param Logger $logger
     * @return void
     */
    public static function setLogger(Logger $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * @param string $logs
     * @param string $class
     * @param string $func
     * @param string $line
     * @throws Exception
     */
    public static function info(string $logs, string $class = '', string $func = '', string $line = ''): void
    {
        // generates a backtrace
        [$class, $func, $line] = self::getTraceInfo($class, $func, $line);

        // logging
        $logger = self::getLogger();
        $logger->info(self::makeMessage($logs, $class, $func, $line));
    }

    /**
     * @param string $logs
     * @param string $class
     * @param string $func
     * @param string $line
     * @throws Exception
     */
    public static function error(string $logs, string $class = '', string $func = '', string $line = ''): void
    {
        // generates a backtrace
        [$class, $func, $line] = self::getTraceInfo($class, $func, $line);

        // logging
        $logger = self::getLogger();
        $logger->error(self::makeMessage($logs, $class, $func, $line));
    }

    /**
     * @param string $logs
     * @param string $class
     * @param string $func
     * @param string $line
     * @throws Exception
     */
    public static function debug(string $logs, string $class = '', string $func = '', string $line = ''): void
    {
        // generates a backtrace
        [$class, $func, $line] = self::getTraceInfo($class, $func, $line);

        // logging
        $logger = self::getLogger();
        $logger->debug(self::makeMessage($logs, $class, $func, $line));
    }

    /**
     * 쿼리와 관련된 logging (추가 정보 로깅하기 위해 별도 method 분리)
     * @param string $queryType : SELECT, INSERT, UPDATE, DELETE
     * @param string $query
     * @param array|null $bindings
     * @throws Exception
     * @since SYN-315
     */
    public static function query(string $queryType, string $query, ?array $bindings): void
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
        $logger = self::getLogger();
        $logger->pushProcessor(new QueryProcessor($queryType, $query, $bindings, $executeInfo)); // query 관련 processor 추가
        $logger->debug(self::makeMessage($query, $class, $func, $line), $context);
        $logger->popProcessor(); // 마지막 processor 제거
    }

    /**
     * @param Throwable $ex
     * @param null $reference
     * @throws Exception
     */
    public static function exception(Throwable $ex, $reference = null): void
    {
        // generates a backtrace
        [$class, $func, $line] = self::getTraceInfo('', '', '');

        // generates context
        $context = ['log_type' => 'exception'];

        if (!empty($reference)) {
            $context['reference'] = $reference;
        }
        
        // logging
        $logger = self::getLogger();
        $logger->pushProcessor(new ExceptionProcessor($ex)); // exception 관련 processor 추가
        $logger->error(self::makeMessage($ex->getMessage(), $class, $func, $line), $context);
        $logger->popProcessor(); // 마지막 processor 제거
    }

    /**
     * @return Logger
     * @throws Exception
     */
    private static function getLogger(): Logger
    {
        if (empty(self::$logger)) {
            self::$logger = (new CreateLogger())->getLogger();
        }
        
        return self::$logger;
    }

    /**
     * @param string $logs
     * @param string $class
     * @param string $func
     * @param string $line
     * @return string
     */
    private static function makeMessage(string $logs, string $class, string $func, string $line): string
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
    private static function getTraceInfo(string $class, string $func, string $line): array
    {
        // generates a backtrace
        $debugInfo = debug_backtrace();

        return [
            empty($class) ?$debugInfo[2]['class'] :$class,
            empty($func) ?$debugInfo[2]['function'] :$func,
            empty($line) ?$debugInfo[1]['line'] :$line
        ];
    }
}
