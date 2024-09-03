<?php
namespace middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use Exception;
use DateTime;

use libraries\log\LogMessage;
use libraries\request\ParamParser;
use libraries\request\HeaderParser;

class Common
{
    private $isLoggingResponse;

    /**
     * Common constructor.
     * @param ContainerInterface $ci
     * @param bool $isLoggingResponse
     */
    public function __construct(ContainerInterface $ci, bool $isLoggingResponse = true)
    {
        $this->isLoggingResponse = $isLoggingResponse;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     * @throws Exception
     */
    public function __invoke(Request $request, Response $response, callable $next): Response
    {
        // set transaction key
        $transcationKey = $this->makeTransactionKey();
        $request = $request->withAttribute('transaction_key', $transcationKey);
        define('TRANSACTION_KEY', $transcationKey);

        // get http headers
        $headerParser = new HeaderParser($request);
        $request = $request->withAttribute('headers', $headerParser);
        LogMessage::info('[middleware]headers::' . $headerParser->getHeaders(true));

        // get params in http body
        $paramParser = new ParamParser($request);
        $request = $request->withAttribute('params', $paramParser);
        LogMessage::info('[middleware]request::' . $paramParser->getParam(true));

        // call next middleware or application
        $response = $next($request, $response);

        // logging http response body contents
        if (true === $this->isLoggingResponse) {
            LogMessage::info('[middleware]response::' . $response->getBody());
        }

        return $response;
    }

    /**
     * @return string
     * @throws Exception
     */
    private function makeTransactionKey(): string
    {
        return hash('md5', (new DateTime())->format('Uu').random_bytes(32));
    }
}
