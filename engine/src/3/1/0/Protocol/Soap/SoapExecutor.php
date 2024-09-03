<?php
namespace Ntuple\Synctree\Protocol\Soap;

use Exception;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Util\Markup\Xml\XmlDecoder;
use SoapClient;
use SoapFault;
use SoapHeader;
use Throwable;

class SoapExecutor
{
    private $logger;
    private $client;
    private $wsdl;
    private $options;
    private $headers;
    private $functionName;
    private $arguments;
    private $isConvertXml;
    private $isEnableLogging;
    private $isReplacePrefix;

    /**
     * SoapExecutor constructor.
     * @param LogMessage $logger
     */
    public function __construct(LogMessage $logger)
    {
        $this->logger = $logger;
        $this->options = [];
        $this->headers = [];
        $this->arguments =[];
        $this->isConvertXml = false;
        $this->isEnableLogging = true;
        $this->isReplacePrefix = false;
    }

    /**
     *  soap wsdl url
     * @param string $wsdl
     * @return SoapExecutor
     */
    public function setWsdl(string $wsdl): SoapExecutor
    {
        $this->wsdl = $wsdl;
        return $this;
    }

    /**
     * @param array $options
     * @return SoapExecutor
     */
    public function setOptions(array $options): SoapExecutor
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param array $header
     * @param bool $mustunderstand
     * @return SoapExecutor
     */
    public function setHeaders(string $namespace, string $name, array $header = [], bool $mustunderstand = false): SoapExecutor
    {
        $this->headers[] = new SoapHeader($namespace, $name, $header, $mustunderstand);
        return $this;
    }

    /**
     * @param string $functionName
     * @return SoapExecutor
     */
    public function setFunctioName(string $functionName): SoapExecutor
    {
        $this->functionName = $functionName;
        return $this;
    }

    /**
     * @param array $arguments
     * @return SoapExecutor
     */
    public function setArguments(array $arguments): SoapExecutor
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * @param bool $isConvertXml
     * @param bool $isReplcePrefix
     * @return SoapExecutor
     */
    public function isConvertXml(bool $isConvertXml, bool $isReplcePrefix = false): SoapExecutor
    {
        $this->isConvertXml = $isConvertXml;
        $this->isReplacePrefix = $isReplcePrefix;
        return $this;
    }

    /**
     * @param bool $isEnableLogging
     * @return SoapExecutor
     */
    public function isEnableLogging(bool $isEnableLogging): SoapExecutor
    {
        $this->isEnableLogging = $isEnableLogging;
        return $this;
    }

    /**
     * soap init and request
     * @return array
     * @throws Throwable
     */
    public function execute(): array
    {
        // get options
        $options =  $this->getOptions($this->options);

        try {
            // create client
            $this->client = $this->createClient($options);

            // set headers
            foreach ($this->headers as $header) {
                $this->client->__setSoapHeaders($header);
            }

            // call soap
            $this->client->__soapCall($this->functionName, [$this->arguments]);

            // return response after make response data
            return $this->makeResponse();
        } catch (SoapFault $ex) {
            $this->logger->exception($ex, 'faultcode:'.$ex->faultcode.', faultstring: '.$ex->faultstring.', wsdl:'.$this->wsdl);
            throw $ex;
        } catch (Throwable $ex) {
            $this->logger->exception($ex, 'wsdl:'.$this->wsdl.', options:'. json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE, 512));
            throw $ex;
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function makeResponse(): array
    {
        // logging
        $this->logging();

        // parse response header
        $responseHeaders = $this->parseRawHeaders($this->client->__getLastResponseHeaders(), 'response');

        $statusCode = (int)$responseHeaders['status']['code'];
        unset($responseHeaders['status']);

        return [
            $statusCode,
            $responseHeaders,
            $this->getResponseBody()
        ];
    }

    /**
     * @param array $options
     * @return SignedSoapClient|SoapClient
     * @throws SoapFault
     */
    private function createClient(array $options)
    {
        if (array_key_exists('ssl', $options)) {
            return new SignedSoapClient($this->wsdl, $options);
        }

        return new SoapClient($this->wsdl, $options);
    }

    /**
     * @param array $options
     * @return array
     */
    private function getOptions(array $options): array
    {
        // add trace option
        $options['trace'] = 1;
        return $options;
    }

    /**
     * @param string $message
     * @param string $type
     * @return array
     */
    private function parseRawHeaders(string $message, string $type): array
    {
        $resData = [];
        $headers = explode("\r\n", $message);

        // parse header status
        $resData['status'] = $this->parseHeaderStatus($headers[0], $type);
        unset($headers[0]);

        // set each entity
        foreach ($headers as $entity) {
            if (empty($entity)) {
                continue;
            }
            $entitys = preg_split('/: \s*/', $entity);
            $resData[$entitys[0]] = $entitys[1];
        }

        return $resData;
    }

    /**
     * @param string $message
     * @param string $type
     * @return array
     */
    private function parseHeaderStatus(string $message, string $type): array
    {
        $resData = [];
        switch ($type) {
            case 'request':
                [$resData['method'], $resData['uri'], $protocol] = explode(' ', $message);

                $resData['protocol'] = [];
                [$resData['protocol']['protocol'], $resData['protocol']['version']] = explode('/', $protocol);
                break;

            case 'response':
                if (strpos($message, 'HTTP') === 0) {
                    [$resData['protocol'], $resData['code'], $resData['text']] = explode(' ', $message);
                }
                break;
        }

        return $resData;
    }

    /**
     * @throws Exception
     */
    private function logging(): void
    {
        try {
            if (false === $this->isEnableLogging) {
                return;
            }

            if (APP_ENV === APP_ENV_PRODUCTION) {
                $this->logger->info( $this->format('{hostname} {req_header_User-Agent} - [{date_common_log}] \"{method} {target} HTTP/{version}\" {code} {res_header_Content-Length}'));
                return;
            }

            $this->logger->info( $this->format('[request::{request}][response::{response}]'));
        } catch (Throwable $ex) {
            $this->logger->exception($ex);
        }
    }

    /**
     * @return mixed
     */
    private function getResponseBody()
    {
        $responseBody = $this->client->__getLastResponse();

        if ($this->isConvertXml === false) {
            return $responseBody;
        }

        $xmlDecoder = new XmlDecoder($responseBody);
        $xmlDecoder->setReplacePrefixByEmptyStringInNodeNames($this->isReplacePrefix);
        return $xmlDecoder->convert();
    }

    /**
     * @param string $template
     * @return string|string[]|null
     */
    private function format(string $template)
    {
        $reqHeaders = [];
        $resHeaders = [];

        if (APP_ENV === APP_ENV_PRODUCTION) {
            $reqHeaders = $this->parseRawHeaders($this->client->__getLastRequestHeaders(), 'request');
            $resHeaders = $this->parseRawHeaders($this->client->__getLastResponseHeaders(), 'response');
        }

        $_this = $this;
        return preg_replace_callback(
            '/{\s*([A-Za-z_\-\.0-9]+)\s*}/',
            static function (array $matches) use ($_this, $reqHeaders, $resHeaders) {
                $result = '';
                switch ($matches[1]) {
                    case 'request':
                        $result = $_this->client->__getLastRequestHeaders().$_this->client->__getLastRequest();
                        break;
                    case 'response':
                        $result = $_this->client->__getLastResponseHeaders().$_this->client->__getLastResponse();
                        break;
                    case 'date_common_log':
                        $result = date('d/M/Y:H:i:s O');
                        break;
                    case 'method':
                        $result = isset($reqHeaders['status']['method']) && !empty($reqHeaders['status']['method']) ?$reqHeaders['status']['method'] :'NULL';
                        break;
                    case 'version':
                        $result = isset($reqHeaders['status']['protocol']['version']) && !empty($reqHeaders['status']['protocol']['version']) ?$reqHeaders['status']['protocol']['version'] :'NULL';
                        break;
                    case 'uri':
                    case 'url':
                    case 'target':
                        $result = isset($reqHeaders['status']['uri']) && !empty($reqHeaders['status']['uri']) ?$reqHeaders['status']['uri'] :'NULL';
                        break;
                    case 'host':
                        $result = isset($reqHeaders['Host']) && !empty($reqHeaders['Host']) ?$reqHeaders['Host'] :'NULL';
                        break;
                    case 'req_header_User-Agent':
                        $result = isset($reqHeaders['User-Agent']) && !empty($reqHeaders['User-Agent']) ?$reqHeaders['User-Agent'] :'NULL';
                        break;
                    case 'hostname':
                        $result = gethostname();
                        break;
                    case 'code':
                        $result = isset($resHeaders['status']['code']) && !empty($resHeaders['status']['code']) ?$resHeaders['status']['code'] :'NULL';
                        break;
                    case 'res_header_Content-Length':
                        $result = isset($resHeaders['Content-Length']) && !empty($resHeaders['Content-Length']) ?$resHeaders['Content-Length'] :'NULL';
                        break;
                }

                return $result;
            },
            $template
        );
    }
}