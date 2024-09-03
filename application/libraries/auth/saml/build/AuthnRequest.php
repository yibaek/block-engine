<?php
namespace libraries\auth\saml\build;

use Exception;
use libraries\auth\saml\SamlConstants;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\Protocol\AuthnRequest as AuthnRequestProtocol;
use LightSaml\Model\Protocol\NameIDPolicy;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use Throwable;

class AuthnRequest
{
    private $config;
    private $request;
    private $response;

    /**
     * AuthnRequest constructor.
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $config
     */
    public function __construct(RequestInterface $request, ResponseInterface $response, array $config = [])
    {
        $this->request = $request;
        $this->response = $response;

        // set config
        $this->config = $this->addDefaultConfig($config);
    }

    /**
     * @return bool
     * @throws Exception|Throwable
     */
    public function generate(): bool
    {
        try {
            // make authn request
            if(!$authnRequest=$this->makeAuthnRequest($this->request)) {
                return false;
            }

            // set signature
            if ($signature = BuildUtil::makeSignature($this->config)) {
                $authnRequest->setSignature($signature);
            }

            // set reponse
            $this->response->setContents(BuildUtil::makeMessageXML($this->config, $authnRequest));
            return true;
        } catch (Throwable $ex) {
            throw $ex;
        }
    }

    /**
     * @param string $authnRequest
     * @return bool
     * @throws Throwable
     */
    public function validate(string $authnRequest): bool
    {
        try {
            return true;
        } catch (Throwable $ex) {
            throw $ex;
        }
    }

    /**
     * @param RequestInterface $request
     * @return AuthnRequestProtocol|null
     * @throws Exception
     */
    private function makeAuthnRequest(RequestInterface $request): ?AuthnRequestProtocol
    {
        // check acs url [required]
        if (!$acsUrl = $request->request('acs_url', false)) {
            $this->response->setError(400, 'invalid_request', 'The ACS-url was not specified in the request');
            return null;
        }

        // check protocol binding [required]
        if (!$protocolBinding = $request->request('protocol_binding', false)) {
            $this->response->setError(400, 'invalid_request', 'The protocol-binding was not specified in the request');
            return null;
        }

        // check issuer [required]
        if (!$issuer = $request->request('issuer', false)) {
            $this->response->setError(400, 'invalid_request', 'The issuer was not specified in the request');
            return null;
        }

        // check requestId [optional]
        $requestID = $request->request('request_id', BuildUtil::generateID());

        // check issueInstant [optional]
        $issusInstant = $request->request('issue_instant', time());

        // generate AuthnRequest
        $authnRequest = new AuthnRequestProtocol();
        $authnRequest->setAssertionConsumerServiceURL($acsUrl)
            ->setProtocolBinding($protocolBinding)
            ->setID($requestID)
            ->setIssueInstant($issusInstant)
            ->setIssuer(new Issuer($issuer));

        // check nameIdPolicy
        if ($nameIDPolicy = $request->request('nameid_policy')) {
            if (isset($nameIDPolicy['format'])) {
                $authnRequest->setNameIDPolicy(new NameIDPolicy($nameIDPolicy['format'], $nameIDPolicy['allow-create'] ?? null));
            }
        } else {
            $authnRequest->setNameIDPolicy(new NameIDPolicy(SamlConstants::NAME_ID_FORMAT_UNSPECIFIED));
        }

        // check destination
        if ($destination = $request->request('destination', false)) {
            $authnRequest->setDestination($destination);
        }

        // check providerName
        if ($providerName = $request->request('provider_name', false)) {
            $authnRequest->setProviderName($providerName);
        }

        // check isPassive
        if ($isPassive = $request->request('is_passive', false)) {
            $authnRequest->setIsPassive($isPassive);
        }

        return $authnRequest;
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