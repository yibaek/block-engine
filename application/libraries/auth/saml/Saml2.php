<?php
namespace libraries\auth\saml;

use libraries\auth\Request;
use libraries\auth\Response;
use libraries\auth\saml\build\AuthnRequest;
use libraries\auth\saml\build\Assertion;
use Throwable;

class Saml2
{
    public const AUTHORIZATION_TYPE = 'saml2';

    private $config;
    private $request;
    private $response;
    private $validation;

    /**
     * Saml2 constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->request = Request::createFromGlobals();
        $this->response = new Response();
        $this->validation = false;

        // set config
        $this->config = $this->addDefaultConfig($config);
    }

    /**
     * @return $this
     * @throws Throwable
     */
    public function generateAuthnRequest(): self
    {
        // make authn request
        (new AuthnRequest($this->request, $this->response, $this->config))->generate();
        return $this;
    }

    /**
     * @return $this
     * @throws Throwable
     */
    public function generateAssertion(): self
    {
        // make assertion
        (new Assertion($this->request, $this->response, $this->config))->generate();
        return $this;
    }

    public function validateAuthnRequest(): self
    {
        return $this;
    }

    /**
     * @return $this
     * @throws Throwable
     */
    public function validateAssertion(): self
    {
        // make assertion
        $this->validation = (new Assertion($this->request, $this->response, $this->config))->validate();
        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->response->getHttpHeaders();
    }

    /**
     * @return array|string|null
     */
    public function getBodys()
    {
        if ($this->validation) {
            return $this->response->getParameters();
        }

        return $this->isResponseSuccess() ?$this->response->getContents() :$this->response->getParameters();
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->validation;
    }

    /**
     * @return bool
     */
    private function isResponseSuccess(): bool
    {
        return $this->getStatusCode() === 200;
    }

    /**
     * @param array $config
     * @return array
     */
    private function addDefaultConfig(array $config): array
    {
        return $config;
    }
}