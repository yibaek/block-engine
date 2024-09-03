<?php
namespace libraries\auth\oauth\saml;

use libraries\auth\saml\build\Assertion;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use Throwable;

class Saml2Assertion implements Saml2AssertionInterface
{
    private $request;
    private $response;
    private $config;

    /**
     * Saml2Assertion constructor.
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $config
     */
    public function __construct(RequestInterface $request, ResponseInterface $response, array $config = [])
    {
        $this->request = $request;
        $this->response = $response;
        $this->config = $config;
    }

    /**
     * @return bool
     * @throws Throwable
     */
    public function validate(): bool
    {
        return (new Assertion($this->request, $this->response, $this->config))->validate();
    }
}