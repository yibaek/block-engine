<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;

use models\s3\S3Mgr;
use models\redis\RedisMgr;
use models\dynamo\DynamoDbMgr;
use libraries\util\CommonUtil;
use libraries\log\CreateLogger;
use libraries\log\ConsoleLogger;
use libraries\log\ConsoleLogFileHandler;
use libraries\log\LogMessage;
use libraries\log\processor\ExceptionProcessor;

/** @var Slim\App $app */

// DIC configuration
$container = $app->getContainer();

// storage mgr
$container['storage'] = static function (ContainerInterface $container) {
    return new S3Mgr();
};

// redis mgr
$container['redis'] = static function (ContainerInterface $container) {
    return new RedisMgr();
};

// dynamodb mgr
$container['dynamo'] = static function (ContainerInterface $container) {
    return new DynamoDbMgr();
};

// monolog
$container['logger'] = static function (ContainerInterface $container) {
    return (new CreateLogger())->getLogger();
};

// exception handler
$container['errorHandler'] = static function (ContainerInterface $container) {
    return static function (Request $request, Response $response, Exception $exception) use ($container) {
        // logging
        $logger = $container->get('logger');
        $logger->pushProcessor(new ExceptionProcessor($exception)); // exception 관련 processor 추가
        $logger->error('[errorHandler(line:'.__LINE__.')]' . $exception->getMessage(), ['log_type' => 'exception']);
        $logger->popProcessor(); // 마지막 processor 제거

        // provider 체크
        $credential = CommonUtil::getCredentialConfig('provider');
        $isProviderNtuple = ($credential['provider'] ?? 'ntuple') === 'ntuple';

        // provider는 기본 ntuple일 것이라고 전제한다. 그렇지 않을 경우는 on-prem으로 간주하고, mydata 규격 사용한다.
        // refs #5514
        if ($isProviderNtuple) {
            $status_code = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400;
            $response = $response->withStatus($status_code);
            $response = $response->withAddedHeader('Content-Type', 'text/plain');
            if (method_exists($exception, 'render')) {
                $response = $response->withAddedHeader('Content-Type', 'application/json');
                $response->getBody()->write(json_encode($exception->render(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            } else {
                $response->getBody()->write('An Exception Occurred.');
            }
        } else {
            $response = $response->withStatus(500);
            $response = $response->withAddedHeader('Content-Type', 'application/json');
            $body = [
                'rsp_code' => 50001,
                'rsp_msg' => 'System Error',
            ];
            $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
        }

        return $response;
    };
};

// php error handler
$container['phpErrorHandler'] = static function (ContainerInterface $container) {
    return static function (Request $request, Response $response, Error $error) use ($container) {
        // logging
        $logger = $container->get('logger');
        $logger->pushProcessor(new ExceptionProcessor($error)); // exception 관련 processor 추가
        $logger->error('[errorHandler(line:'.__LINE__.')]' . $error->getMessage(), ['log_type' => 'exception']);
        $logger->popProcessor(); // 마지막 processor 제거

        $response = $response->withStatus(400);
        $response = $response->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('An Exception Occurred.');
        return $response;
    };
};

// 404 not found error handler
$container['notFoundHandler'] = static function (ContainerInterface $container) {
    return static function (Request $request, Response $response) use ($container) {
        $response = $response->withStatus(404);
        $response = $response->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write("Not Found '".$request->getUri()->getPath()."' router");
        return $response;
    };
};


$container[ConsoleLogger::class] = static function (ContainerInterface $container): ConsoleLogger {
    return new ConsoleLogger();
};

$container[ConsoleLogFileHandler::class] = static function (ContainerInterface $container): ConsoleLogFileHandler {
    return new ConsoleLogFileHandler($container->get(ConsoleLogger::class));
};

// set logger in LogMessage Class
LogMessage::setLogger($container->get('logger'));