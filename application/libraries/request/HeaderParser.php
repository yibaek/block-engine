<?php
namespace libraries\request;

use JsonException;
use Psr\Http\Message\ServerRequestInterface as Request;

class HeaderParser
{
    private $request;
    private $headers;
    private $originHeaders;

    /**
     * HeaderParser constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->originHeaders = $this->request->getHeaders();
        $this->headers = $this->parseHeaders($this->originHeaders);
    }

    /**
     * @return array
     */
    public function getOriginHeaders(): array
    {
        return $this->originHeaders;
    }

    /**
     * @param bool $isJson
     * @return false|string|array
     * @throws JsonException
     */
    public function getHeaders(bool $isJson = false)
    {
        return $isJson ?json_encode($this->headers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) :$this->headers;
    }

    /**
     * rebuild request headers
     * @param array $headers
     * @return array
     */
    private function parseHeaders(array $headers): array
    {
        $resData = [];
        foreach ($headers as $key => $header) {
            $resData[$key] = $header[0];
        }

        return $this->replaceRawHeaders($resData);
    }

    /**
     * @param array $headers
     * @return array
     */
    private function replaceRawHeaders(array $headers): array
    {
        $resData = [];
        $prefix = '/\AHTTP_/';
        foreach ($headers as $key => $value) {
            if (preg_match($prefix, $key)) {
                $key = preg_replace($prefix, '', $key);
            }

            $containKeys = explode('_', $key);
            if (count($containKeys) > 0 && strlen($key) > 2) {
                foreach ($containKeys as $subKey => $subValue) {
                    $containKeys[$subKey] = ucfirst($subValue);
                }
                $key = implode('-', $containKeys);
            }
            $resData[$key] = $value;
        }

        return $resData;
    }
}