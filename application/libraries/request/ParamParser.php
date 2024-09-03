<?php
namespace libraries\request;

use JsonException;
use libraries\request\util\XmlDecoder;
use Psr\Http\Message\ServerRequestInterface as Request;

class ParamParser
{
    private $request;
    private $parsedBody;

    /**
     * ParserBody constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->parsedBody = $this->parseBody($request->getBody()->getContents());
    }

    /**
     * @param bool $isJson
     * @return array|mixed|string|null
     * @throws JsonException
     */
    public function getParam(bool $isJson = false)
    {
        // get querystring
        $params = $this->getQueryParams();

        // get parsed body
        $parsedBody = $this->getParsedBody();
        if ($parsedBody) {
            if (is_array($parsedBody)) {
                $params = array_merge($params, (array)$parsedBody);
            } else {
                $params = $parsedBody;
            }
        }

        return $isJson && is_array($params) ?json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) :$params;
    }

    /**
     * @return array
     * @throws JsonException
     */
    public function getParamWithArguments(): array
    {
        return array_merge($this->getParam(), $this->getArguments());
    }

    /**
     * @return array
     */
    public function getQueryParams(): array
    {
        $params = $this->request->getQueryParams();
        array_shift($params);
        return $params;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        $routeInfo = $this->request->getAttribute('routeInfo');
        return isset($routeInfo[2]) && !empty($routeInfo[2]) ?$routeInfo[2] :[];
    }

    /**
     * @return array|mixed|string|null
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * @param string|null $mediaType
     * @return string|null
     */
    private function getMediaType(string $mediaType = null): ?string
    {
        if (null === $mediaType) {
            return $mediaType;
        }

        // look for a media type with a structured syntax suffix (RFC 6839)
        $parts = explode('+', $mediaType);
        if (count($parts) >= 2) {
            $mediaType = 'application/' . $parts[count($parts)-1];
        }

        return $mediaType;
    }

    /**
     * @param string $contents
     * @return array|mixed|string|null
     */
    private function parseBody(string $contents)
    {
        if (null === ($mediaType=$this->getMediaType($this->request->getMediaType()))) {
            return $contents;
        }

        switch ($mediaType) {
            case 'application/json';
                try {
                    $result = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($result)) {
                        return null;
                    }
                    return $result;
                } catch (JsonException $e) {
                    return null;
                }

            case 'text/xml';
            case 'application/xml';
                return (new XmlDecoder($contents))->setReplacePrefixByEmptyStringInNodeNames(false)->convert();

            case 'application/x-www-form-urlencoded';
                parse_str($contents, $data);
                return $data;

            default:
                return $contents;
        }
    }
}